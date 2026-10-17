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

class ImportMobilCatalogue extends Command
{
    protected $signature = 'import:mobil {--json=pdfs/mobil_price_list_260930_174310_extracted.json} {--pdf=MOBIL Price list 8th May 2026--pdf_260930_174310.pdf}';
    protected $description = 'Import Mobil lubricants price list (McLarens Lubricants Limited) from extracted JSON';

    public function handle(): int
    {
        $jsonPath = base_path($this->option('json'));
        $pdfFile  = $this->option('pdf');

        if (!file_exists($jsonPath)) {
            $this->error("JSON not found: {$jsonPath}");
            return 1;
        }

        $rows = json_decode(file_get_contents($jsonPath), true);
        $this->info('Loaded ' . count($rows) . ' rows from JSON.');

        $branch   = Branch::where('code', 'MAIN')->first() ?? Branch::first();
        $supplier = Supplier::firstOrCreate(
            ['name' => 'McLarens Lubricants Limited'],
            ['is_active' => true, 'branch_id' => $branch?->id]
        );
        $partBrand    = PartBrand::firstOrCreate(['name' => 'MOBIL']);
        $quality      = QualityType::firstOrCreate(['name' => 'International']);
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
            $name   = trim($row['name'] ?? '');
            if (!$partNo || !$name) { $skipped++; $bar->advance(); continue; }

            $catName      = $row['category'] ?? 'Lubricants';
            $partCategory = PartCategory::firstOrCreate(['name' => $catName]);

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
                    'description'      => 'McLarens Lubricants — MOBIL',
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
                'supplier'    => 'McLarens Lubricants Limited — MOBIL',
                'status'      => 'imported',
                'total_rows'  => count($rows),
                'imported'    => $imported,
                'skipped'     => $skipped,
                'imported_at' => now(),
                'notes'       => 'Scanned PDF rendered to PNG; data transcribed visually. Single price = Retail with VAT; purchase = retail/1.18. JSON: ' . basename($this->option('json')),
            ]
        );

        return 0;
    }
}
