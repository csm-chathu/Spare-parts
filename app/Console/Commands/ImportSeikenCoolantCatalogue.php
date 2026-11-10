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

class ImportSeikenCoolantCatalogue extends Command
{
    protected $signature = 'import:seiken-coolant {--json=pdfs/seiken_coolant_extracted.json} {--pdf=SEIKEN COOLANT 10042026_261109_091902.pdf}';
    protected $description = 'Import Seiken Coolant & Brake Fluid (Bear Brand) from extracted JSON';

    public function handle(): int
    {
        $jsonPath = base_path($this->option('json'));

        if (!file_exists($jsonPath)) {
            $this->error("JSON not found: {$jsonPath}");
            $this->line('Run: python pdfs/extract_seiken_coolant.py');
            return 1;
        }

        $rows = json_decode(file_get_contents($jsonPath), true);
        $this->info('Loaded ' . count($rows) . ' rows from JSON.');

        $branch   = Branch::where('code', 'MAIN')->first() ?? Branch::first();
        $supplier = Supplier::firstOrCreate(
            ['name' => 'Semini Motors (Pvt) Ltd'],
            ['is_active' => true, 'branch_id' => $branch?->id]
        );
        $partBrand = PartBrand::firstOrCreate(['name' => 'Seiken']);
        $quality   = QualityType::firstOrCreate(['name' => 'Japan']);

        $universalType  = VehicleType::firstOrCreate(['name' => 'Car']);
        $universalBrand = Brand::firstOrCreate(['name' => 'Universal']);
        $universalModel = VehicleModel::firstOrCreate([
            'brand_id' => $universalBrand->id, 'vehicle_type_id' => $universalType->id, 'name' => 'Universal',
        ]);

        $categoryCache = [];
        $imported = 0; $skipped = 0;
        $bar = $this->output->createProgressBar(count($rows));
        $bar->start();

        foreach ($rows as $row) {
            $partNo = trim($row['part_number'] ?? '');
            if (!$partNo) { $skipped++; $bar->advance(); continue; }

            $catName      = $row['category'] ?? 'Chemicals';
            $partCategory = $categoryCache[$catName] ??= PartCategory::firstOrCreate(['name' => $catName]);

            $sku = 'SEIKC-' . strtoupper(preg_replace('/[^A-Z0-9]/i', '', $partNo));

            Product::updateOrCreate(
                ['sku' => $sku],
                [
                    'vehicle_type_id'  => $universalType->id,
                    'brand_id'         => $universalBrand->id,
                    'model_id'         => $universalModel->id,
                    'part_category_id' => $partCategory->id,
                    'quality_type_id'  => $quality->id,
                    'supplier_id'      => $supplier->id,
                    'branch_id'        => $branch?->id,
                    'part_brand_id'    => $partBrand->id,
                    'part_number'      => $partNo,
                    'name'             => $row['name'] ?? 'Seiken Chemical',
                    'description'      => ($row['name'] ?? '') . ' | Bear Brand by Seiken Chemicals',
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
                'supplier'    => 'Semini Motors (Pvt) Ltd — Seiken Coolant & Brake Fluid',
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
