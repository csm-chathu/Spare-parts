<?php

namespace App\Console\Commands;

use App\Models\Brand;
use App\Models\Branch;
use App\Models\PartBrand;
use App\Models\PartCategory;
use App\Models\PdfImportLog;
use App\Models\Product;
use App\Models\QualityType;
use App\Models\Supplier;
use App\Models\VehicleModel;
use App\Models\VehicleType;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class ImportNpwCatalogue extends Command
{
    protected $signature = 'import:npw {--json=pdfs/npw_2_260930_110546_extracted.json} {--pdf=NPW-2_260930_110546.pdf}';
    protected $description = 'Import NPW Water Pump catalogue from extracted JSON';

    private array $knownBrands = [
        'DAIHATSU', 'TOYOTA', 'NISSAN', 'HONDA', 'MITSUBISHI', 'SUZUKI',
        'ISUZU', 'MAZDA', 'FORD', 'HYUNDAI', 'SUBARU', 'KIA', 'HINO',
        'KUBOTA', 'VOLVO',
    ];

    public function handle(): int
    {
        $jsonPath = base_path($this->option('json'));

        if (!file_exists($jsonPath)) {
            $this->error("JSON not found: {$jsonPath}");
            $this->line('Run: python pdfs/extract_npw.py "pdfs/NPW-2_260930_110546.pdf"');
            return 1;
        }

        $rows = json_decode(file_get_contents($jsonPath), true);
        $this->info('Loaded ' . count($rows) . ' rows from JSON.');

        $branch    = Branch::where('code', 'MAIN')->first() ?? Branch::first();
        $supplier  = Supplier::firstOrCreate(
            ['email' => 'info@douglas-sons.lk'],
            ['name' => 'Douglas & Sons (Pvt) Ltd.', 'is_active' => true, 'branch_id' => $branch?->id]
        );
        $partBrand    = PartBrand::firstOrCreate(['name' => 'NPW']);
        $quality      = QualityType::firstOrCreate(['name' => 'Japan']);
        $partCategory = PartCategory::firstOrCreate(['name' => 'Cooling System']);

        $imported = 0;
        $skipped  = 0;
        $bar = $this->output->createProgressBar(count($rows));
        $bar->start();

        foreach ($rows as $row) {
            $brandName = $this->normalizeBrand($row['brand'] ?? '');
            if (!$brandName) {
                $skipped++;
                $bar->advance();
                continue;
            }

            $vehicleType = $this->resolveVehicleType($row['models'] ?? '', $brandName);
            $brand       = Brand::firstOrCreate(['name' => Str::title(strtolower($brandName))]);
            $modelName   = $this->extractModel($row['models'] ?? '');
            $model       = VehicleModel::firstOrCreate([
                'brand_id'        => $brand->id,
                'vehicle_type_id' => $vehicleType->id,
                'name'            => $modelName,
            ]);

            $sku         = 'NPW-' . strtoupper(preg_replace('/[^A-Z0-9]/i', '', $row['part_number']));
            $gmb         = !empty($row['gmb_number']) ? ' | GMB: ' . $row['gmb_number'] : '';
            $description = $brandName . ' ' . trim($row['models'] ?? '') . $gmb;

            Product::updateOrCreate(
                ['sku' => $sku],
                [
                    'vehicle_type_id'  => $vehicleType->id,
                    'brand_id'         => $brand->id,
                    'model_id'         => $model->id,
                    'part_category_id' => $partCategory->id,
                    'quality_type_id'  => $quality->id,
                    'supplier_id'      => $supplier->id,
                    'branch_id'        => $branch?->id,
                    'part_brand_id'    => $partBrand->id,
                    'part_number'      => $row['part_number'],
                    'name'             => $row['name'] ?? 'Water Pump',
                    'description'      => $description,
                    'purchase_price'   => $row['purchase_price'],
                    'selling_price'    => $row['selling_price'],
                    'stock_quantity'   => 0,
                    'min_stock_level'  => 1,
                    'is_active'        => true,
                ]
            );

            $imported++;
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info("Done. Imported: {$imported} | Skipped: {$skipped}");

        PdfImportLog::updateOrCreate(
            ['filename' => $this->option('pdf')],
            [
                'supplier'    => 'Douglas & Sons (Pvt) Ltd. — NPW',
                'status'      => 'imported',
                'total_rows'  => count($rows),
                'imported'    => $imported,
                'skipped'     => $skipped,
                'imported_at' => now(),
                'notes'       => 'JSON: ' . basename($this->option('json')),
            ]
        );

        return 0;
    }

    private function normalizeBrand(string $raw): ?string
    {
        $upper = strtoupper(trim($raw));
        foreach ($this->knownBrands as $b) {
            if ($upper === $b) return $b;
        }
        return $upper ?: null;
    }

    private function resolveVehicleType(string $models, string $brand): VehicleType
    {
        $m = strtoupper($models);
        if (in_array($brand, ['HINO']) || preg_match('/\bFUSO\b|\bCANTER\b|\bELF\b|\bNPR\b|\bNKR\b|\bFORWARD\b|\bCONDOR\b/', $m)) {
            return VehicleType::firstOrCreate(['name' => 'Truck']);
        }
        if (preg_match('/HIACE|CARAVAN|EVERY|TOWNACE|VANETTE|URVAN/', $m)) {
            return VehicleType::firstOrCreate(['name' => 'Van']);
        }
        return VehicleType::firstOrCreate(['name' => 'Car']);
    }

    private function extractModel(string $desc): string
    {
        if (!$desc) return 'Universal';
        $words = preg_split('/\s+/', trim($desc));
        return implode(' ', array_slice($words, 0, 4)) ?: 'Universal';
    }
}
