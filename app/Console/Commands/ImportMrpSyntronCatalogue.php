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

class ImportMrpSyntronCatalogue extends Command
{
    protected $signature = 'import:mrp-syntron {--json=pdfs/mrp_oct_1st_261005_110904_1_extracted.json} {--pdf=MRP OCT 1ST_261005_110904 (1).pdf}';
    protected $description = 'Import MRP International (SYNTRON/POWERTRON/VEEDOL) lubricants from extracted JSON';

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
            ['name' => 'MRP International (Pvt) Ltd.'],
            ['is_active' => true, 'branch_id' => $branch?->id]
        );
        $quality      = QualityType::firstOrCreate(['name' => 'International']);
        $partCategory = PartCategory::firstOrCreate(['name' => 'Lubricants']);
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

            $name = trim($row['name'] ?? '');
            if (!$name) { $skipped++; $bar->advance(); continue; }

            $pbName    = $row['part_brand'] ?? 'VEEDOL';
            $partBrand = PartBrand::firstOrCreate(['name' => $pbName]);

            $sku = 'MRP-' . strtoupper(preg_replace('/[^A-Z0-9]/i', '', $partNo));

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
                    'description'      => 'MRP International — ' . $pbName,
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
                'supplier'    => 'MRP International — SYNTRON/POWERTRON/VEEDOL',
                'status'      => 'imported',
                'total_rows'  => count($rows),
                'imported'    => $imported,
                'skipped'     => $skipped,
                'imported_at' => now(),
                'notes'       => 'Retail prices; purchase = retail / 1.18. JSON: ' . basename($this->option('json')),
            ]
        );

        return 0;
    }
}
