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

class ImportKybShocksCatalogue extends Command
{
    protected $signature = 'import:kyb-shocks {--json=pdfs/kyb_shocks_4_260930_113542_extracted.json} {--pdf=KYB SHOCKS-4_260930_113542.pdf}';
    protected $description = 'Import KYB Shock Absorber catalogue from extracted JSON';

    private array $knownBrands = [
        'TOYOTA', 'NISSAN', 'HONDA', 'MITSUBISHI', 'SUZUKI', 'ISUZU',
        'MAZDA', 'DAIHATSU', 'SUBARU', 'HYUNDAI', 'KIA', 'FORD', 'HINO', 'PERODUA',
    ];

    public function handle(): int
    {
        $jsonPath = base_path($this->option('json'));

        if (!file_exists($jsonPath)) {
            $this->error("JSON not found: {$jsonPath}");
            $this->line('Run: python pdfs/extract_kyb_shocks.py "pdfs/KYB SHOCKS-4_260930_113542.pdf"');
            return 1;
        }

        $rows = json_decode(file_get_contents($jsonPath), true);
        $this->info('Loaded ' . count($rows) . ' rows from JSON.');

        $branch    = Branch::where('code', 'MAIN')->first() ?? Branch::first();
        $supplier  = Supplier::firstOrCreate(
            ['email' => 'info@douglas-sons.lk'],
            ['name' => 'Douglas & Sons (Pvt) Ltd.', 'is_active' => true, 'branch_id' => $branch?->id]
        );
        $partBrand    = PartBrand::firstOrCreate(['name' => 'KYB']);
        $partCategory = PartCategory::firstOrCreate(['name' => 'Suspension Parts']);

        $qualityCache = [];
        $brandCache   = [];

        $imported = 0;
        $skipped  = 0;
        $bar = $this->output->createProgressBar(count($rows));
        $bar->start();

        foreach ($rows as $row) {
            $brandName = strtoupper(trim($row['brand'] ?? ''));
            if (!in_array($brandName, $this->knownBrands)) {
                $skipped++;
                $bar->advance();
                continue;
            }

            $qualityName = ($row['quality'] ?? '') === 'Taiwan' ? 'Taiwan' : 'Japan';
            $quality     = $qualityCache[$qualityName] ??= QualityType::firstOrCreate(['name' => $qualityName]);

            $vehicleTypeName = $this->resolveType($row['vehicle_type'] ?? 'Car', $row['models'] ?? '');
            $vehicleType     = VehicleType::firstOrCreate(['name' => $vehicleTypeName]);
            $brand           = $brandCache[$brandName] ??= Brand::firstOrCreate(['name' => Str::title(strtolower($brandName))]);
            $modelName       = $this->extractModel($row['models'] ?? '');
            $model           = VehicleModel::firstOrCreate([
                'brand_id'        => $brand->id,
                'vehicle_type_id' => $vehicleType->id,
                'name'            => $modelName,
            ]);

            $rawPart = $row['raw_part'] ?? $row['part_number'];
            $sku     = 'KYB-' . strtoupper(preg_replace('/[^A-Z0-9\/\-]/i', '', $row['part_number']));
            $description = 'Fits: ' . $brandName . ' ' . trim($row['models'] ?? '');
            $description .= ' | ' . ($row['name'] ?? 'Shock Absorber');

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
                    'part_number'      => $rawPart,
                    'name'             => 'Shock Absorber',
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
                'supplier'    => 'Douglas & Sons (Pvt) Ltd. — KYB',
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

    private function resolveType(string $sectionType, string $models): string
    {
        $s = strtoupper($sectionType);
        $m = strtoupper($models);
        if (str_contains($s, 'PICKUP') || str_contains($s, 'CAB')
            || preg_match('/HILUX|VIGO|REVO|L200|TRITON|RANGER|D-MAX|NAVARA/', $m)) {
            return 'Pickup';
        }
        if (str_contains($s, 'VAN') || str_contains($s, 'COASTER')
            || preg_match('/HIACE|CARAVAN|EVERY|TOWNACE|URVAN/', $m)) {
            return 'Van';
        }
        if (str_contains($s, 'TRUCK') || preg_match('/\bNPR\b|\bNKR\b|\bELF\b|\bCANTER\b|\bFUSO\b/', $m)) {
            return 'Truck';
        }
        if (str_contains($s, 'JEEP') || preg_match('/PRADO|LAND CRUISER|SURF|PAJERO|PATROL/', $m)) {
            return 'SUV';
        }
        return 'Car';
    }

    private function extractModel(string $desc): string
    {
        if (!$desc) return 'Universal';
        // Strip position markers "(F)", "(R)" etc.
        $desc = preg_replace('/\s*\([FRL]\)\s*/', ' ', $desc);
        $words = preg_split('/\s+/', trim($desc));
        return implode(' ', array_slice($words, 0, 4)) ?: 'Universal';
    }
}
