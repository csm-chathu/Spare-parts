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

class ImportJsAutoCatalogue extends Command
{
    protected $signature = 'import:js-auto {--json=} {--pdf=}';
    protected $description = 'Import JS Auto Spares / Supply price lists (universal — use for all 6 JS files)';

    private array $knownBrands = [
        'TOYOTA', 'NISSAN', 'HONDA', 'MITSUBISHI', 'SUZUKI', 'ISUZU',
        'MAZDA', 'DAIHATSU', 'SUBARU', 'HYUNDAI', 'KIA', 'FORD',
        'HINO', 'PERODUA', 'SSANGYONG', 'LEXUS', 'LAND ROVER',
        'BMW', 'VOLVO', 'MAN', 'JCB', 'DFSK', 'RENAUT', 'TATA',
        'MAHINDRA', 'INDIAN', 'AUDI', 'VW',
    ];

    public function handle(): int
    {
        $jsonPath = $this->option('json');
        $pdfFile  = $this->option('pdf') ?? basename($jsonPath ?? '');

        if (!$jsonPath || !file_exists(base_path($jsonPath))) {
            $this->error('--json is required and must point to an existing file.');
            $this->line('Example: php artisan import:js-auto --json="pdfs/price_list_-_filters_extracted.json" --pdf="PRICE LIST - filters.pdf"');
            return 1;
        }

        $rows = json_decode(file_get_contents(base_path($jsonPath)), true);
        $this->info('Loaded ' . count($rows) . ' rows from JSON.');

        $branch   = Branch::where('code', 'MAIN')->first() ?? Branch::first();
        $supplier = Supplier::firstOrCreate(
            ['name' => 'JS Auto Spares / Supply'],
            ['is_active' => true, 'branch_id' => $branch?->id]
        );
        $quality = QualityType::firstOrCreate(['name' => 'Local']);

        $imported = 0; $skipped = 0;
        $bar = $this->output->createProgressBar(count($rows));
        $bar->start();

        foreach ($rows as $row) {
            $partNo = trim($row['part_number'] ?? '');
            if (!$partNo) { $skipped++; $bar->advance(); continue; }

            $name = trim($row['name'] ?? '');
            if (!$name) { $skipped++; $bar->advance(); continue; }

            // Category
            $catName      = $row['category'] ?? 'Spare Parts';
            $partCategory = PartCategory::firstOrCreate(['name' => $catName]);

            // Part brand
            $pbName    = $row['part_brand'] ?? 'JS';
            $partBrand = PartBrand::firstOrCreate(['name' => $pbName]);

            // Vehicle brand
            $brandName = strtoupper(trim($row['brand'] ?? 'UNIVERSAL'));
            if (!in_array($brandName, $this->knownBrands)) $brandName = 'Universal';
            $vtName = $row['vehicle_type'] ?? 'Car';

            $vt    = VehicleType::firstOrCreate(['name' => $vtName]);
            $brand = Brand::firstOrCreate(['name' => ucfirst(strtolower($brandName))]);
            $model = VehicleModel::firstOrCreate([
                'brand_id'        => $brand->id,
                'vehicle_type_id' => $vt->id,
                'name'            => 'Universal',
            ]);

            // SKU: use part_number directly (already prefixed by extractor)
            $sku = strtoupper(preg_replace('/[^A-Z0-9\-]/i', '', $partNo));
            if (!$sku) { $skipped++; $bar->advance(); continue; }

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
                    'name'             => $name,
                    'description'      => 'JS Auto Spares | ' . ($row['models'] ?? $name),
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
            ['filename' => $pdfFile],
            [
                'supplier'    => 'JS Auto Spares / Supply',
                'status'      => 'imported',
                'total_rows'  => count($rows),
                'imported'    => $imported,
                'skipped'     => $skipped,
                'imported_at' => now(),
                'notes'       => 'JSON: ' . basename($jsonPath),
            ]
        );

        return 0;
    }
}
