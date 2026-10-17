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

class ImportCwPricelistCatalogue extends Command
{
    protected $signature = 'import:cw-pricelist {--json=pdfs/new_price_list_21_09_2026_extracted.json} {--pdf=NEW PRICE LIST 21.09.2026.pdf}';
    protected $description = 'Import CW Parts multi-category price list from extracted JSON';

    private array $knownBrands = [
        'TOYOTA', 'NISSAN', 'HONDA', 'MITSUBISHI', 'SUZUKI', 'ISUZU',
        'MAZDA', 'DAIHATSU', 'SUBARU', 'HYUNDAI', 'KIA', 'FORD', 'HINO',
        'PERODUA', 'SSANGYONG', 'LEXUS', 'JEEP', 'MG', 'UNIVERSAL',
    ];

    public function handle(): int
    {
        $jsonPath = base_path($this->option('json'));

        if (!file_exists($jsonPath)) {
            $this->error("JSON not found: {$jsonPath}");
            $this->line('Run: python pdfs/extract_cw_pricelist.py "pdfs/NEW PRICE LIST 21.09.2026.pdf"');
            return 1;
        }

        $rows = json_decode(file_get_contents($jsonPath), true);
        $this->info('Loaded ' . count($rows) . ' rows from JSON.');

        $branch   = Branch::where('code', 'MAIN')->first() ?? Branch::first();
        $supplier = Supplier::firstOrCreate(
            ['name' => 'CW Auto Parts'],
            ['is_active' => true, 'branch_id' => $branch?->id]
        );
        $partBrand = PartBrand::firstOrCreate(['name' => 'CW']);
        $quality   = QualityType::firstOrCreate(['name' => 'Genuine']);

        $categoryCache    = [];
        $brandCache       = [];
        $vehicleTypeCache = [];

        $imported = 0;
        $skipped  = 0;
        $bar = $this->output->createProgressBar(count($rows));
        $bar->start();

        foreach ($rows as $row) {
            $partNo    = trim($row['part_number'] ?? '');
            $partName  = $row['name'] ?? 'Auto Part';
            $catName   = $row['category'] ?? 'General';
            $brandName = strtoupper(trim($row['brand'] ?? 'UNIVERSAL'));
            $modelStr  = trim($row['models'] ?? '');
            $vtName    = $row['vehicle_type'] ?? 'Car';

            if (!$partNo) {
                $skipped++;
                $bar->advance();
                continue;
            }

            if (!in_array($brandName, $this->knownBrands)) {
                $brandName = 'UNIVERSAL';
            }

            $partCategory = $categoryCache[$catName]
                ??= PartCategory::firstOrCreate(['name' => $catName]);
            $vehicleType  = $vehicleTypeCache[$vtName]
                ??= VehicleType::firstOrCreate(['name' => $vtName]);
            $brand        = $brandCache[$brandName]
                ??= Brand::firstOrCreate(['name' => Str::title(strtolower($brandName))]);

            $modelName = $this->extractModel($modelStr);
            $model     = VehicleModel::firstOrCreate([
                'brand_id'        => $brand->id,
                'vehicle_type_id' => $vehicleType->id,
                'name'            => $modelName,
            ]);

            $sku = 'CW-' . strtoupper(preg_replace('/[^A-Z0-9]/i', '', $partNo));

            $description = $partName;
            if ($brandName !== 'UNIVERSAL') {
                $description .= ' | Fits: ' . $brandName . ' ' . $modelStr;
            }
            if (!empty($row['oem_number'])) {
                $description .= ' | OEM: ' . $row['oem_number'];
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
                    'part_number'      => $partNo,
                    'name'             => $partName,
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
                'supplier'    => 'CW Auto Parts — Multi-Category Price List',
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

    private function extractModel(string $desc): string
    {
        if (!$desc) return 'Universal';
        $words = preg_split('/[\s\/]+/', trim($desc));
        return implode(' ', array_slice($words, 0, 3)) ?: 'Universal';
    }
}
