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

class ImportPremiumSakuraCatalogue extends Command
{
    protected $signature = 'import:premium-sakura {--json=pdfs/final_updated_price_list_-_21st_aug_2026_260930_141255_extracted.json} {--pdf=Final Updated Price List - 21st Aug 2026_260930_141255.pdf}';
    protected $description = 'Import SAKURA Filters (Premium Auto Parts) from extracted JSON';

    private array $knownBrands = [
        'TOYOTA', 'NISSAN', 'HONDA', 'MITSUBISHI', 'SUZUKI', 'ISUZU',
        'MAZDA', 'DAIHATSU', 'SUBARU', 'HYUNDAI', 'KIA', 'FORD',
        'HINO', 'PERODUA', 'SSANGYONG', 'LEXUS', 'LAND ROVER',
        'BMW', 'VOLVO', 'MAN', 'JCB', 'DFSK', 'RENAUT',
    ];

    public function handle(): int
    {
        $jsonPath = base_path($this->option('json'));

        if (!file_exists($jsonPath)) {
            $this->error("JSON not found: {$jsonPath}");
            $this->line('Run: python pdfs/extract_premium_sakura.py "pdfs/Final Updated Price List - 21st Aug 2026_260930_141255.pdf"');
            return 1;
        }

        $rows = json_decode(file_get_contents($jsonPath), true);
        $this->info('Loaded ' . count($rows) . ' rows from JSON.');

        $branch   = Branch::where('code', 'MAIN')->first() ?? Branch::first();
        $supplier = Supplier::firstOrCreate(
            ['name' => 'Premium Auto Parts (Pvt) Ltd.'],
            ['is_active' => true, 'branch_id' => $branch?->id]
        );
        $partBrand = PartBrand::firstOrCreate(['name' => 'SAKURA']);
        $quality   = QualityType::firstOrCreate(['name' => 'Indonesia']);

        $imported = 0; $skipped = 0;
        $bar = $this->output->createProgressBar(count($rows));
        $bar->start();

        foreach ($rows as $row) {
            $partNo = trim($row['part_number'] ?? '');
            if (!$partNo) { $skipped++; $bar->advance(); continue; }

            $brandName = strtoupper(trim($row['brand'] ?? 'UNIVERSAL'));
            if (!in_array($brandName, $this->knownBrands)) $brandName = 'Universal';

            $vtName  = $row['vehicle_type'] ?? 'Car';
            $vt      = VehicleType::firstOrCreate(['name' => $vtName]);
            $brand   = Brand::firstOrCreate(['name' => ucfirst(strtolower($brandName))]);
            $model   = VehicleModel::firstOrCreate([
                'brand_id'        => $brand->id,
                'vehicle_type_id' => $vt->id,
                'name'            => 'Universal',
            ]);

            $catName  = $row['category'] ?? 'Filters';
            $category = PartCategory::firstOrCreate(['name' => $catName]);

            $sku = 'PRMSAK-' . strtoupper(preg_replace('/[^A-Z0-9]/i', '', $partNo));

            Product::updateOrCreate(
                ['sku' => $sku],
                [
                    'vehicle_type_id'  => $vt->id,
                    'brand_id'         => $brand->id,
                    'model_id'         => $model->id,
                    'part_category_id' => $category->id,
                    'quality_type_id'  => $quality->id,
                    'supplier_id'      => $supplier->id,
                    'branch_id'        => $branch?->id,
                    'part_brand_id'    => $partBrand->id,
                    'part_number'      => $partNo,
                    'name'             => $row['name'] ?? 'Filter',
                    'description'      => 'Fits: ' . ($row['models'] ?? ''),
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
                'supplier'    => 'Premium Auto Parts — SAKURA Filters',
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
}
