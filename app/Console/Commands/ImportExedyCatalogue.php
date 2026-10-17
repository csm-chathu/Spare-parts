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

class ImportExedyCatalogue extends Command
{
    protected $signature = 'import:exedy {--json=pdfs/exedy_2_extracted.json} {--pdf=EXEDY-2.pdf}';
    protected $description = 'Import EXEDY clutch disc & covers catalogue from extracted JSON';

    private array $knownBrands = [
        'DAIHATSU', 'TOYOTA', 'NISSAN', 'HONDA', 'MITSUBISHI', 'SUZUKI',
        'ISUZU', 'MAZDA', 'FORD', 'HYUNDAI', 'SUBARU', 'KIA', 'HINO',
        'UD NISSAN', 'UD', 'FUSO', 'LEYLAND', 'TATA', 'PERODUA', 'GEELY',
    ];

    public function handle(): int
    {
        $jsonPath = base_path($this->option('json'));

        if (!file_exists($jsonPath)) {
            $this->error("JSON not found: {$jsonPath}");
            $this->line("Run: python pdfs/extract_exedy.py pdfs/EXEDY-2.pdf");
            return 1;
        }

        $rows = json_decode(file_get_contents($jsonPath), true);
        $this->info('Loaded ' . count($rows) . ' rows from JSON.');

        $branch    = Branch::where('code', 'MAIN')->first() ?? Branch::first();
        $supplier  = Supplier::firstOrCreate(
            ['email' => 'info@douglas-sons.lk'],
            ['name' => 'Douglas & Sons (Pvt) Ltd.', 'is_active' => true, 'branch_id' => $branch?->id]
        );
        $partBrand = PartBrand::firstOrCreate(['name' => 'EXEDY']);
        $quality   = QualityType::firstOrCreate(['name' => 'Genuine']);

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

            $vehicleType  = $this->resolveVehicleType($row['models'] ?? '', $brandName);
            $brand        = Brand::firstOrCreate(['name' => $brandName]);
            $modelName    = $this->extractFirstModel($row['models'] ?? '');
            $model        = VehicleModel::firstOrCreate([
                'brand_id'        => $brand->id,
                'vehicle_type_id' => $vehicleType->id,
                'name'            => $modelName,
            ]);
            $partCategory = PartCategory::firstOrCreate(['name' => 'Transmission']);

            $sku         = 'EXD-' . strtoupper(preg_replace('/[^A-Z0-9\-]/i', '', $row['part_number']));
            $description = 'Fits: ' . $brandName . ' ' . trim($row['models'] ?? '');

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
                    'name'             => Str::title(strtolower($row['name'])),
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
                'supplier'    => 'Douglas & Sons (Pvt) Ltd. — EXEDY',
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
        $raw = trim($raw);
        $upper = strtoupper($raw);
        foreach ($this->knownBrands as $brand) {
            if ($upper === strtoupper($brand) || str_starts_with($upper, strtoupper($brand))) {
                return strtoupper($brand);
            }
        }
        if (preg_match('/^[A-Z][A-Z\s]{1,18}$/', $raw)) {
            return strtoupper(trim($raw));
        }
        return null;
    }

    private function resolveVehicleType(string $models, string $brand): VehicleType
    {
        $m = strtoupper($models);
        if (in_array($brand, ['HINO', 'UD', 'UD NISSAN', 'FUSO'])) {
            return VehicleType::firstOrCreate(['name' => 'Truck']);
        }
        if (preg_match('/\bTRUCK\b|DYNA|NPR|NQR|ELF|FORWARD|LEYLAND/', $m)) {
            return VehicleType::firstOrCreate(['name' => 'Truck']);
        }
        if (preg_match('/HILUX|L200|TRITON|RANGER|BT.?50|STRADA|BONGO TRUCK/', $m)) {
            return VehicleType::firstOrCreate(['name' => 'Pickup']);
        }
        if (preg_match('/HIACE|CARAVAN|VANETTE|ACTY|FARGO|TOWNACE|SAMBAR|EVERY/', $m)) {
            return VehicleType::firstOrCreate(['name' => 'Van']);
        }
        return VehicleType::firstOrCreate(['name' => 'Car']);
    }

    private function extractFirstModel(string $models): string
    {
        $models = trim($models);
        if (!$models) return 'Unknown';
        $parts = preg_split('/[,\/]|\s{2,}/', $models);
        $first = trim($parts[0] ?? $models);
        $words = preg_split('/\s+/', $first);
        return implode(' ', array_slice($words, 0, 3)) ?: 'Unknown';
    }
}
