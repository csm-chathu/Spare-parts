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

class ImportFujiHoseCatalogue extends Command
{
    protected $signature = 'import:fuji-hose {--json=pdfs/fuji_brake_hose_26_01_2026_extracted.json} {--pdf=FUJI BRAKE HOSE 26.01.2026.pdf}';
    protected $description = 'Import FUJI Brake Hoses from extracted JSON';

    private array $knownBrands = [
        'TOYOTA','NISSAN','HONDA','MITSUBISHI','SUZUKI','ISUZU','MAZDA',
        'DAIHATSU','SUBARU','HYUNDAI','KIA','FORD','HINO','PERODUA','LEXUS',
    ];

    public function handle(): int
    {
        $jsonPath = base_path($this->option('json'));

        if (!file_exists($jsonPath)) {
            $this->error("JSON not found: {$jsonPath}");
            $this->line('Run: python pdfs/extract_fuji_hose.py "pdfs/FUJI BRAKE HOSE 26.01.2026.pdf"');
            return 1;
        }

        $rows = json_decode(file_get_contents($jsonPath), true);
        $this->info('Loaded ' . count($rows) . ' rows from JSON.');

        $branch    = Branch::where('code', 'MAIN')->first() ?? Branch::first();
        $supplier  = Supplier::firstOrCreate(
            ['name' => 'FUJI Japan Distributors'],
            ['is_active' => true, 'branch_id' => $branch?->id]
        );
        $partBrand = PartBrand::firstOrCreate(['name' => 'FUJI']);
        $quality   = QualityType::firstOrCreate(['name' => 'Japan']);

        $categoryCache    = [];
        $brandCache       = [];
        $vehicleTypeCache = [];
        $universalType    = VehicleType::firstOrCreate(['name' => 'Car']);
        $universalBrand   = Brand::firstOrCreate(['name' => 'Universal']);
        $universalModel   = VehicleModel::firstOrCreate([
            'brand_id' => $universalBrand->id, 'vehicle_type_id' => $universalType->id, 'name' => 'Universal',
        ]);

        $imported = 0; $skipped = 0;
        $bar = $this->output->createProgressBar(count($rows));
        $bar->start();

        foreach ($rows as $row) {
            $partNo = trim($row['part_number'] ?? '');
            if (!$partNo) { $skipped++; $bar->advance(); continue; }

            $brandName = strtoupper(trim($row['brand'] ?? ''));
            $catName   = $row['category'] ?? 'Brake Parts';
            $vtName    = $row['vehicle_type'] ?? 'Car';
            $modelStr  = trim($row['models'] ?? '');

            $partCategory = $categoryCache[$catName] ??= PartCategory::firstOrCreate(['name' => $catName]);

            if (in_array($brandName, $this->knownBrands)) {
                $vehicleType = $vehicleTypeCache[$vtName] ??= VehicleType::firstOrCreate(['name' => $vtName]);
                $brand       = $brandCache[$brandName] ??= Brand::firstOrCreate(['name' => Str::title(strtolower($brandName))]);
                $modelName   = $this->extractModel($modelStr);
                $model       = VehicleModel::firstOrCreate([
                    'brand_id' => $brand->id, 'vehicle_type_id' => $vehicleType->id, 'name' => $modelName,
                ]);
            } else {
                $vehicleType = $universalType;
                $brand       = $universalBrand;
                $model       = $universalModel;
            }

            $sku = 'FUJI-' . strtoupper(preg_replace('/[^A-Z0-9]/i', '', $partNo));

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
                    'name'             => $row['name'] ?? 'Brake Hose',
                    'description'      => 'Fits: ' . $modelStr,
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
                'supplier'    => 'FUJI Japan — Brake Hoses',
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
        $words = preg_split('/[\s,\/]+/', trim($desc));
        return implode(' ', array_slice($words, 0, 3)) ?: 'Universal';
    }
}
