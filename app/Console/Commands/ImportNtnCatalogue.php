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

class ImportNtnCatalogue extends Command
{
    protected $signature = 'import:ntn {--json=pdfs/ntn_1_extracted.json} {--pdf=NTN-1.pdf}';
    protected $description = 'Import NTN Brand Bearing catalogue from extracted JSON';

    public function handle(): int
    {
        $jsonPath = base_path($this->option('json'));

        if (!file_exists($jsonPath)) {
            $this->error("JSON not found: {$jsonPath}");
            $this->line('Run: python pdfs/extract_ntn.py pdfs/NTN-1.pdf');
            return 1;
        }

        $rows = json_decode(file_get_contents($jsonPath), true);
        $this->info('Loaded ' . count($rows) . ' rows from JSON.');

        $branch    = Branch::where('code', 'MAIN')->first() ?? Branch::first();
        $supplier  = Supplier::firstOrCreate(
            ['email' => 'info@douglas-sons.lk'],
            ['name' => 'Douglas & Sons (Pvt) Ltd.', 'is_active' => true, 'branch_id' => $branch?->id]
        );
        $partBrand    = PartBrand::firstOrCreate(['name' => 'NTN']);
        $partCategory = PartCategory::firstOrCreate(['name' => 'Bearings']);

        $imported = 0;
        $skipped  = 0;
        $bar = $this->output->createProgressBar(count($rows));
        $bar->start();

        foreach ($rows as $row) {
            $qualityName = ucfirst(strtolower($row['quality'] ?? 'Japan'));
            $quality     = QualityType::firstOrCreate(['name' => $qualityName . ' Made']);

            $brandName   = $row['brand'] ?? 'GENERIC';
            $vtName      = $row['vehicle_type'] ?? 'Car';
            $vehicleType = VehicleType::firstOrCreate(['name' => $vtName]);

            // For generic bearings use a placeholder brand
            if ($brandName === 'GENERIC') {
                $brand = Brand::firstOrCreate(['name' => 'Generic']);
            } else {
                $brand = Brand::firstOrCreate(['name' => Str::title(strtolower($brandName))]);
            }

            $modelDesc = trim($row['models'] ?? '');
            $modelName = $this->extractModelName($modelDesc);
            $model     = VehicleModel::firstOrCreate([
                'brand_id'        => $brand->id,
                'vehicle_type_id' => $vehicleType->id,
                'name'            => $modelName,
            ]);

            $sku         = 'NTN-' . strtoupper(preg_replace('/[^A-Z0-9\-\/]/i', '', $row['part_number']));
            $description = trim($modelDesc);

            if (Product::where('sku', $sku)->exists()) {
                // Duplicate SKU (same part, different quality) — append quality suffix
                $sku .= '-' . strtoupper(substr($row['quality'] ?? 'JP', 0, 2));
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
                    'part_number'      => $row['part_number'],
                    'name'             => 'Bearing',
                    'description'      => $description ?: 'NTN Bearing',
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
                'supplier'    => 'Douglas & Sons (Pvt) Ltd. — NTN',
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

    private function extractModelName(string $desc): string
    {
        if (!$desc) return 'Universal';
        // Take first meaningful phrase (up to comma or slash)
        $first = preg_split('/[,\/]/', $desc)[0] ?? $desc;
        $words = preg_split('/\s+/', trim($first));
        return implode(' ', array_slice($words, 0, 4)) ?: 'Universal';
    }
}
