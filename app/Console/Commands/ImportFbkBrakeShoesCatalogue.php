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

class ImportFbkBrakeShoesCatalogue extends Command
{
    protected $signature = 'import:fbk-brake-shoes {--json=pdfs/fbk_brake_shoes-3_260930_142503_extracted.json} {--pdf=FBK BRAKE SHOES-3_260930_142503.pdf}';
    protected $description = 'Import FBK Brake Shoes (Douglas & Sons) from extracted JSON';

    private array $knownBrands = [
        'TOYOTA', 'NISSAN', 'HONDA', 'MITSUBISHI', 'SUZUKI', 'ISUZU',
        'MAZDA', 'DAIHATSU', 'SUBARU', 'HYUNDAI', 'KIA', 'FORD',
        'HINO', 'PERODUA', 'SSANGYONG', 'LEXUS',
    ];

    public function handle(): int
    {
        $jsonPath = base_path($this->option('json'));

        if (!file_exists($jsonPath)) {
            $this->error("JSON not found: {$jsonPath}");
            $this->line('Run: python pdfs/extract_fbk_brake_shoes.py "pdfs/FBK BRAKE SHOES-3_260930_142503.pdf"');
            return 1;
        }

        $rows = json_decode(file_get_contents($jsonPath), true);
        $this->info('Loaded ' . count($rows) . ' rows from JSON.');

        $branch   = Branch::where('code', 'MAIN')->first() ?? Branch::first();
        $supplier = Supplier::firstOrCreate(
            ['name' => 'Douglas & Sons (Pvt) Ltd.'],
            ['is_active' => true, 'branch_id' => $branch?->id]
        );
        $partBrand    = PartBrand::firstOrCreate(['name' => 'FBK']);
        $quality      = QualityType::firstOrCreate(['name' => 'Genuine']);
        $partCategory = PartCategory::firstOrCreate(['name' => 'Brake Parts']);

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

            $sku = 'FBKS-' . strtoupper(preg_replace('/[^A-Z0-9]/i', '', $partNo));

            Product::updateOrCreate(
                ['sku' => $sku],
                [
                    'vehicle_type_id'  => $vt->id,
                    'brand_id'         => $brand->id,
                    'model_id'         => $model->id,
                    'part_category_id' => $partCategory->id,
                    'quality_type_id'  => $quality->id,
                    'supplier_id'      => $supplier->id,
                    'branch_id'        => $branch?->id,
                    'part_brand_id'    => $partBrand->id,
                    'part_number'      => $partNo,
                    'name'             => 'Brake Shoe',
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
                'supplier'    => 'Douglas & Sons — FBK Brake Shoes',
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
