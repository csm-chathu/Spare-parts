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

class ImportGspCatalogue extends Command
{
    protected $signature = 'import:gsp {--json=pdfs/gsp_1_extracted.json} {--pdf=GSP-1.pdf}';
    protected $description = 'Import GSP CV Joints catalogue from extracted JSON';

    private array $knownBrands = [
        'DAIHATSU', 'TOYOTA', 'NISSAN', 'HONDA', 'MITSUBISHI', 'SUZUKI',
        'ISUZU', 'MAZDA', 'FORD', 'HYUNDAI', 'SUBARU', 'KIA', 'AUDI', 'PERODUA',
    ];

    public function handle(): int
    {
        $jsonPath = base_path($this->option('json'));

        if (!file_exists($jsonPath)) {
            $this->error("JSON not found: {$jsonPath}");
            $this->line('Run: python pdfs/extract_gsp.py pdfs/GSP-1.pdf');
            return 1;
        }

        $rows = json_decode(file_get_contents($jsonPath), true);
        $this->info('Loaded ' . count($rows) . ' rows from JSON.');

        $branch    = Branch::where('code', 'MAIN')->first() ?? Branch::first();
        $supplier  = Supplier::firstOrCreate(
            ['email' => 'info@douglas-sons.lk'],
            ['name' => 'Douglas & Sons (Pvt) Ltd.', 'is_active' => true, 'branch_id' => $branch?->id]
        );
        $partBrand    = PartBrand::firstOrCreate(['name' => 'GSP']);
        $quality      = QualityType::firstOrCreate(['name' => 'Genuine']);
        $partCategory = PartCategory::firstOrCreate(['name' => 'Suspension Parts']);

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

            $vehicleType = $this->resolveVehicleType($row['models'] ?? '');
            $brand       = Brand::firstOrCreate(['name' => Str::title(strtolower($brandName))]);
            $modelName   = $this->extractFirstModel($row['models'] ?? '');
            $model       = VehicleModel::firstOrCreate([
                'brand_id'        => $brand->id,
                'vehicle_type_id' => $vehicleType->id,
                'name'            => $modelName,
            ]);

            $sku         = 'GSP-' . strtoupper(preg_replace('/[^A-Z0-9\-]/i', '', $row['part_number']));
            $description = 'Fits: ' . $brandName . ' ' . trim($row['models'] ?? '');
            if (!empty($row['catalog_number'])) {
                $description .= ' | Cat: ' . $row['catalog_number'];
            }

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
                    'name'             => 'CV Joint',
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
                'supplier'    => 'Douglas & Sons (Pvt) Ltd. — GSP',
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
        if (preg_match('/^[A-Z][A-Z\s]{1,18}$/', $raw)) {
            return strtoupper(trim($raw));
        }
        return null;
    }

    private function resolveVehicleType(string $models): VehicleType
    {
        $m = strtoupper($models);
        if (preg_match('/HILUX|VIGO|REVO|L200|TRITON|RANGER|D-MAX|NAVARA|PICKUP D21/', $m)) {
            return VehicleType::firstOrCreate(['name' => 'Pickup']);
        }
        if (preg_match('/HIACE|CARAVAN|EVERY|TOWNACE|VANETTE|URVAN/', $m)) {
            return VehicleType::firstOrCreate(['name' => 'Van']);
        }
        if (preg_match('/\bNPR\b|\bNKR\b|\bELF\b|\bCANTER\b/', $m)) {
            return VehicleType::firstOrCreate(['name' => 'Truck']);
        }
        return VehicleType::firstOrCreate(['name' => 'Car']);
    }

    private function extractFirstModel(string $models): string
    {
        $models = trim($models);
        if (!$models) return 'Unknown';
        $parts = preg_split('/,|\s{2,}/', $models);
        $first = trim($parts[0] ?? $models);
        $first = preg_replace('/\s+(4WD|2WD|ABS)\s*$/i', '', $first);
        $words = preg_split('/\s+/', trim($first));
        return implode(' ', array_slice($words, 0, 3)) ?: 'Unknown';
    }
}
