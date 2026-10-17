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

class ImportMchanixCatalogue extends Command
{
    protected $signature = 'import:mchanix {--json=pdfs/mchanix_extracted.json} {--pdf=MCHANIX-1.pdf}';
    protected $description = 'Import MCHANIX parts catalogue from extracted JSON into the products table';

    private array $brandMap = [
        'TOYOYA'        => 'TOYOTA',
        'TOYOTAHIACE'   => 'TOYOTA',
        'TOYOTACAMRY'   => 'TOYOTA',
        'TOYOTADYNA/'   => 'TOYOTA',
        'TOYOTAHILUX'   => 'TOYOTA',
        'TOYOTARAV4'    => 'TOYOTA',
        'TOYOTARUSH'    => 'TOYOTA',
        'RAV4SXA1#'     => 'TOYOTA',
        'ECHO,YARIS'    => 'TOYOTA',
        'NISSANNAVARA'  => 'NISSAN',
        'NISSANSUNNY'   => 'NISSAN',
        'HONDACIVIC'    => 'HONDA',
        'HONDAFITGP5'   => 'HONDA',
        'MISUBISHI'     => 'MITSUBISHI',
        'MITSUBISHIL200'=> 'MITSUBISHI',
        'MITSUBISHI,'   => 'MITSUBISHI',
        'SUZUKISWIFT'   => 'SUZUKI',
        'ISUZUNPR71'    => 'ISUZU',
        'ISUZUNPRRE'    => 'ISUZU',
        'ISUZUNQR'      => 'ISUZU',
        'ISUZUNQRLEFT'  => 'ISUZU',
        'MAZDA/FORD'    => 'MAZDA',
        'FORD,MAZDA'    => 'MAZDA',
        '323-'          => 'MAZDA',
    ];

    private array $knownBrands = [
        'TOYOTA', 'NISSAN', 'HONDA', 'MITSUBISHI', 'SUZUKI',
        'ISUZU', 'DAIHATSU', 'MAZDA', 'FORD', 'HYUNDAI', 'SUBARU',
    ];

    public function handle(): int
    {
        $jsonPath = base_path($this->option('json'));

        if (!file_exists($jsonPath)) {
            $this->error("JSON not found: {$jsonPath}");
            $this->line("Run: python pdfs/extract_mchanix.py  to generate it first.");
            return 1;
        }

        $rows = json_decode(file_get_contents($jsonPath), true);
        $this->info('Loaded ' . count($rows) . ' rows from JSON.');

        $branch  = Branch::where('code', 'MAIN')->first() ?? Branch::first();
        $supplier = Supplier::firstOrCreate(
            ['email' => 'info@douglas-sons.lk'],
            [
                'name'      => 'Douglas & Sons (Pvt) Ltd.',
                'is_active' => true,
                'branch_id' => $branch?->id,
            ]
        );
        $partBrand = PartBrand::firstOrCreate(['name' => 'MCHANIX']);
        $quality   = QualityType::firstOrCreate(['name' => 'Genuine']);

        $imported = 0;
        $skipped  = 0;
        $bar = $this->output->createProgressBar(count($rows));
        $bar->start();

        foreach ($rows as $row) {
            $brandName = $this->normalizeBrand($row['brand']);

            if (!$brandName) {
                $skipped++;
                $bar->advance();
                continue;
            }

            $vehicleType = $this->resolveVehicleType($row['models']);
            $brand       = Brand::firstOrCreate(['name' => $brandName]);
            $modelName   = $this->extractFirstModel($row['models']);
            $model       = VehicleModel::firstOrCreate([
                'brand_id'        => $brand->id,
                'vehicle_type_id' => $vehicleType->id,
                'name'            => $modelName,
            ]);
            $partCategory = $this->resolvePartCategory($row['name']);

            $sku         = 'MCH-' . strtoupper(preg_replace('/[^A-Z0-9\-]/i', '', $row['part_number']));
            $description = 'Fits: ' . trim($row['brand'] . ' ' . $row['models']);

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
        $this->info("Done. Imported: {$imported} | Skipped (unknown brand): {$skipped}");

        $pdfFile = $this->option('pdf');
        PdfImportLog::updateOrCreate(
            ['filename' => $pdfFile],
            [
                'supplier'    => 'Douglas & Sons (Pvt) Ltd. — MCHANIX',
                'status'      => 'imported',
                'total_rows'  => count($rows),
                'imported'    => $imported,
                'skipped'     => $skipped,
                'imported_at' => now(),
                'notes'       => "JSON: " . basename($this->option('json')),
            ]
        );

        return 0;
    }

    private function normalizeBrand(string $raw): ?string
    {
        $raw = trim($raw);

        if (isset($this->brandMap[$raw])) {
            return $this->brandMap[$raw];
        }

        $upper = strtoupper($raw);
        foreach ($this->knownBrands as $brand) {
            if (str_starts_with($upper, $brand)) {
                return $brand;
            }
        }

        // Accept short all-caps names that look like brands
        if (preg_match('/^[A-Z][A-Z\s]{1,18}$/', $raw)) {
            return strtoupper(trim($raw));
        }

        return null;
    }

    private function resolveVehicleType(string $models): VehicleType
    {
        $m = strtoupper($models);

        if (preg_match('/\bTRUCK\b|DYNA|NPR|NQR|TITAN|CANTER|ELF|NKR/', $m)) {
            return VehicleType::firstOrCreate(['name' => 'Truck']);
        }
        if (preg_match('/HILUX|L200|TRITON|RANGER|BT50|NAVARA|D-MAX|STRADA|FRONTIER|PICK.?UP/', $m)) {
            return VehicleType::firstOrCreate(['name' => 'Pickup']);
        }
        if (preg_match('/HIACE|CARAVAN|VANETTE|LARGO|URVAN|DELICA|STARWAGON|REGIUS/', $m)) {
            return VehicleType::firstOrCreate(['name' => 'Van']);
        }

        return VehicleType::firstOrCreate(['name' => 'Car']);
    }

    private function extractFirstModel(string $models): string
    {
        $models = trim($models);
        if (!$models) return 'Unknown';

        // Split on comma or double-space, take first token
        $parts = preg_split('/,|\s{2,}/', $models);
        $first = trim($parts[0] ?? $models);

        // Limit to 2 words max for readability
        $words = preg_split('/\s+/', $first);
        $first = implode(' ', array_slice($words, 0, 2));

        return $first ?: 'Unknown';
    }

    private function resolvePartCategory(string $partName): PartCategory
    {
        $n = strtoupper($partName);

        if (str_contains($n, 'ENGINE MOUNT') || str_contains($n, 'GEAR BOX') || str_contains($n, 'AIR HOSE')) {
            return PartCategory::firstOrCreate(['name' => 'Engine Parts']);
        }

        // Strut mounts, bushes, control arms, spring parts → Suspension
        return PartCategory::firstOrCreate(['name' => 'Suspension Parts']);
    }
}
