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

class ImportIteCatalogue extends Command
{
    protected $signature = 'import:ite {--json=pdfs/ite_2_extracted.json} {--pdf=ITE-2.pdf}';
    protected $description = 'Import ITE Gasket catalogue from extracted JSON';

    private array $knownBrands = [
        'TOYOTA', 'NISSAN', 'HONDA', 'MITSUBISHI', 'SUZUKI', 'ISUZU',
        'MAZDA', 'DAIHATSU', 'FORD', 'HYUNDAI', 'SUBARU', 'KIA', 'HINO',
        'KUBOTA', 'PERKINS',
    ];

    public function handle(): int
    {
        $jsonPath = base_path($this->option('json'));

        if (!file_exists($jsonPath)) {
            $this->error("JSON not found: {$jsonPath}");
            $this->line('Run: python pdfs/extract_ite.py pdfs/ITE-2.pdf');
            return 1;
        }

        $rows = json_decode(file_get_contents($jsonPath), true);
        $this->info('Loaded ' . count($rows) . ' rows from JSON.');

        $branch    = Branch::where('code', 'MAIN')->first() ?? Branch::first();
        $supplier  = Supplier::firstOrCreate(
            ['email' => 'info@douglas-sons.lk'],
            ['name' => 'Douglas & Sons (Pvt) Ltd.', 'is_active' => true, 'branch_id' => $branch?->id]
        );
        $partBrand    = PartBrand::firstOrCreate(['name' => 'ITE']);
        $quality      = QualityType::firstOrCreate(['name' => 'Genuine']);
        $partCategory = PartCategory::firstOrCreate(['name' => 'Engine Parts']);

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

            $gasketName  = $row['name'] ?? 'Gasket Full Set';
            $sku         = 'ITE-' . strtoupper(preg_replace('/[^A-Z0-9\-]/i', '', $row['part_number']));
            $description = $brandName . ' ' . trim($row['models'] ?? '');

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
                    'name'             => $gasketName,
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
                'supplier'    => 'Douglas & Sons (Pvt) Ltd. — ITE',
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

    private function resolveVehicleType(string $desc, string $brand): VehicleType
    {
        $d = strtoupper($desc);
        if (in_array($brand, ['HINO']) || preg_match('/\bFD\b|\bQD\b|\bSD\b|\bTD\b|\bCD\b|NPR|NKR/', $d)) {
            return VehicleType::firstOrCreate(['name' => 'Truck']);
        }
        return VehicleType::firstOrCreate(['name' => 'Car']);
    }

    private function extractModel(string $desc): string
    {
        if (!$desc) return 'Universal';
        // Engine code is the first meaningful token
        $words = preg_split('/\s+/', trim($desc));
        return implode(' ', array_slice($words, 0, 3)) ?: 'Universal';
    }
}
