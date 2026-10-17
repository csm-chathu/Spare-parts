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

class ImportNbcBallsCatalogue extends Command
{
    protected $signature = 'import:nbc-balls {--json=pdfs/nbc_ball_bearing_price_list_2025_250301_152734_261001_130408_extracted.json} {--pdf=NBC ball bearing price list 2025_250301_152734_261001_130408.pdf}';
    protected $description = 'Import NBC standard ball bearings from extracted JSON';

    public function handle(): int
    {
        $jsonPath = base_path($this->option('json'));

        if (!file_exists($jsonPath)) {
            $this->error("JSON not found: {$jsonPath}");
            return 1;
        }

        $rows = json_decode(file_get_contents($jsonPath), true);
        $this->info('Loaded ' . count($rows) . ' rows from JSON.');

        $branch   = Branch::where('code', 'MAIN')->first() ?? Branch::first();
        $supplier = Supplier::firstOrCreate(
            ['name' => 'NBC Bearings Lanka (Pvt) Ltd.'],
            ['is_active' => true, 'branch_id' => $branch?->id]
        );
        $partBrand    = PartBrand::firstOrCreate(['name' => 'NBC']);
        $quality      = QualityType::firstOrCreate(['name' => 'India']);
        $partCategory = PartCategory::firstOrCreate(['name' => 'Bearings']);
        $vt           = VehicleType::firstOrCreate(['name' => 'Car']);
        $brand        = Brand::firstOrCreate(['name' => 'Universal']);
        $model        = VehicleModel::firstOrCreate([
            'brand_id' => $brand->id, 'vehicle_type_id' => $vt->id, 'name' => 'Universal',
        ]);

        $imported = 0; $skipped = 0;
        $bar = $this->output->createProgressBar(count($rows));
        $bar->start();

        foreach ($rows as $row) {
            $partNo = trim($row['part_number'] ?? '');
            if (!$partNo) { $skipped++; $bar->advance(); continue; }

            $sku = 'NBC-' . strtoupper(preg_replace('/[^A-Z0-9]/i', '', $partNo));

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
                    'name'             => 'Ball Bearing',
                    'description'      => 'NBC Standard Ball Bearing ' . $partNo,
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
                'supplier'    => 'NBC Bearings Lanka — Standard Ball Bearings',
                'status'      => 'imported',
                'total_rows'  => count($rows),
                'imported'    => $imported,
                'skipped'     => $skipped,
                'imported_at' => now(),
                'notes'       => 'Partial extraction — OCR quality limited. JSON: ' . basename($this->option('json')),
            ]
        );

        return 0;
    }
}
