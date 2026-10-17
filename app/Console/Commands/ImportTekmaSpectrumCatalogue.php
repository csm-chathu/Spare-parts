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

class ImportTekmaSpectrumCatalogue extends Command
{
    protected $signature = 'import:tekma-spectrum {--json=pdfs/tekma_spectrum_extracted.json} {--pdf=WhatsApp Image 2026-10-05 at 11.11.02 AM.jpeg}';
    protected $description = 'Import Tekma/Spectrum lubricants and care products (Spectrum Trading Company) from extracted JSON';

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
            ['name' => 'Spectrum Trading Company (Pvt) Ltd.'],
            ['is_active' => true, 'branch_id' => $branch?->id]
        );
        $quality = QualityType::firstOrCreate(['name' => 'International']);
        $vt      = VehicleType::firstOrCreate(['name' => 'Car']);
        $brand   = Brand::firstOrCreate(['name' => 'Universal']);
        $model   = VehicleModel::firstOrCreate([
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

            $pbName    = $row['part_brand'] ?? 'SPECTRUM';
            $partBrand = PartBrand::firstOrCreate(['name' => $pbName]);

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
                    'description'      => $pbName . ' — Spectrum Trading Company (Stafford Group)',
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
                'supplier'    => 'Spectrum Trading Company — Tekma / Care Products',
                'status'      => 'imported',
                'total_rows'  => count($rows),
                'imported'    => $imported,
                'skipped'     => $skipped,
                'imported_at' => now(),
                'notes'       => 'Tekma diesel/gear oils + Spectrum care products. Purchase = price without VAT. JSON: ' . basename($this->option('json')),
            ]
        );

        return 0;
    }
}
