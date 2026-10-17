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

class ImportZzBrakeCatalogue extends Command
{
    protected $signature = 'import:zzbrake {--json=pdfs/zandz_brake_shoes_and_pads_extracted.json} {--pdf=Z&z BRAKE SHOES & PADS.pdf}';
    protected $description = 'Import Z&Z Brake Shoes & Pads catalogue from extracted JSON';

    private array $knownBrands = [
        'MITSUBISHI', 'TOYOTA', 'NISSAN', 'HONDA', 'SUZUKI', 'ISUZU',
        'MAZDA', 'FORD', 'HYUNDAI', 'SUBARU', 'KIA', 'DAIHATSU', 'PERODUA', 'MICRO',
    ];

    public function handle(): int
    {
        $jsonPath = base_path($this->option('json'));

        if (!file_exists($jsonPath)) {
            $this->error("JSON not found: {$jsonPath}");
            $this->line('Run: python pdfs/extract_zzbrake.py "pdfs/Z&z BRAKE SHOES & PADS.pdf"');
            return 1;
        }

        $rows = json_decode(file_get_contents($jsonPath), true);
        $this->info('Loaded ' . count($rows) . ' rows from JSON.');

        $branch    = Branch::where('code', 'MAIN')->first() ?? Branch::first();
        $supplier  = Supplier::firstOrCreate(
            ['email' => 'info@douglas-sons.lk'],
            ['name' => 'Douglas & Sons (Pvt) Ltd.', 'is_active' => true, 'branch_id' => $branch?->id]
        );
        $partBrand = PartBrand::firstOrCreate(['name' => 'Z&Z']);
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

            $section      = $row['section'] ?? 'Brake Shoes';
            $vehicleType  = $this->resolveVehicleType($row['models'] ?? '', $brandName);
            $brand        = Brand::firstOrCreate(['name' => Str::title(strtolower($brandName))]);
            $modelName    = $this->extractFirstModel($row['models'] ?? '');
            $model        = VehicleModel::firstOrCreate([
                'brand_id'        => $brand->id,
                'vehicle_type_id' => $vehicleType->id,
                'name'            => $modelName,
            ]);
            $partCategory = PartCategory::firstOrCreate(['name' => 'Brake Parts']);

            $sku         = 'ZZB-' . strtoupper(preg_replace('/[^A-Z0-9\-]/i', '', str_replace(' ', '', $row['part_number'])));
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
                    'name'             => $section,
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
                'supplier'    => 'Douglas & Sons (Pvt) Ltd. — Z&Z',
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
        $raw   = trim($raw);
        $upper = strtoupper($raw);
        foreach ($this->knownBrands as $brand) {
            if ($upper === $brand || str_starts_with($upper, $brand)) {
                return $brand;
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
        if (preg_match('/\bNPR\b|\bNKR\b|\bELF\b|\bROSA\b|\bCANTER\b|\bDYNA\b|\bFORWARD\b/', $m)) {
            return VehicleType::firstOrCreate(['name' => 'Truck']);
        }
        if (preg_match('/HILUX|L200|TRITON|RANGER|STRADA/', $m)) {
            return VehicleType::firstOrCreate(['name' => 'Pickup']);
        }
        if (preg_match('/HIACE|CARAVAN|VANETTE|EVERY|TOWNACE|LITE ?ACE/', $m)) {
            return VehicleType::firstOrCreate(['name' => 'Van']);
        }
        return VehicleType::firstOrCreate(['name' => 'Car']);
    }

    private function extractFirstModel(string $models): string
    {
        $models = trim($models);
        if (!$models) return 'Unknown';
        $parts = preg_split('/,|\s{2,}/', $models);
        $first = trim($parts[0] ?? $models);
        $first = preg_replace('/\s+(4WD|2WD|NEW|RH|LH|F\/R)\s*$/i', '', $first);
        $words = preg_split('/\s+/', trim($first));
        return implode(' ', array_slice($words, 0, 3)) ?: 'Unknown';
    }
}
