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

class ImportNskCatalogue extends Command
{
    protected $signature = 'import:nsk {--json=pdfs/nsk_2_260930_110718_extracted.json} {--pdf=NSK-2_260930_110718.pdf}';
    protected $description = 'Import NSK Brand Bearings catalogue from extracted JSON';

    private array $knownBrands = [
        'TOYOTA', 'NISSAN', 'HONDA', 'MITSUBISHI', 'SUZUKI', 'ISUZU',
        'MAZDA', 'DAIHATSU', 'SUBARU', 'HYUNDAI', 'KIA', 'HINO', 'UNIVERSAL',
    ];

    public function handle(): int
    {
        $jsonPath = base_path($this->option('json'));

        if (!file_exists($jsonPath)) {
            $this->error("JSON not found: {$jsonPath}");
            $this->line('Run: python pdfs/extract_nsk.py "pdfs/NSK-2_260930_110718.pdf"');
            return 1;
        }

        $rows = json_decode(file_get_contents($jsonPath), true);
        $this->info('Loaded ' . count($rows) . ' rows from JSON.');

        $branch    = Branch::where('code', 'MAIN')->first() ?? Branch::first();
        $supplier  = Supplier::firstOrCreate(
            ['email' => 'info@douglas-sons.lk'],
            ['name' => 'Douglas & Sons (Pvt) Ltd.', 'is_active' => true, 'branch_id' => $branch?->id]
        );
        $partBrand    = PartBrand::firstOrCreate(['name' => 'NSK']);
        $partCategory = PartCategory::firstOrCreate(['name' => 'Bearings']);

        $qualityCache = [];
        $brandCache   = [];

        $imported = 0;
        $skipped  = 0;
        $bar = $this->output->createProgressBar(count($rows));
        $bar->start();

        foreach ($rows as $row) {
            $partNo    = trim($row['part_number'] ?? '');
            $qualityRaw = strtoupper(trim($row['quality'] ?? 'JAPAN'));

            if (!$partNo) {
                $skipped++;
                $bar->advance();
                continue;
            }

            $qualityName = match ($qualityRaw) {
                'INDONESIA' => 'Indonesia',
                'POLAND'    => 'Poland',
                'TAIWAN'    => 'Taiwan',
                default     => 'Japan',
            };
            $quality = $qualityCache[$qualityName] ??= QualityType::firstOrCreate(['name' => $qualityName]);

            $brandName = strtoupper(trim($row['brand'] ?? 'UNIVERSAL'));
            if (!in_array($brandName, $this->knownBrands)) {
                $brandName = 'UNIVERSAL';
            }

            $vehicleType = VehicleType::firstOrCreate(['name' => 'Car']);
            $brand       = $brandCache[$brandName] ??= Brand::firstOrCreate(['name' => Str::title(strtolower($brandName))]);
            $modelName   = $this->extractModel($row['models'] ?? '');
            $model       = VehicleModel::firstOrCreate([
                'brand_id'        => $brand->id,
                'vehicle_type_id' => $vehicleType->id,
                'name'            => $modelName,
            ]);

            $skuBase = 'NSK-' . strtoupper(preg_replace('/[^A-Z0-9\/\-\.]/i', '', $partNo));
            // Append quality suffix if SKU already exists with different quality
            $sku = $skuBase;
            $suffix = match ($qualityRaw) {
                'INDONESIA' => '-ID',
                'POLAND'    => '-PL',
                'TAIWAN'    => '-TW',
                default     => '-JP',
            };
            $existing = Product::where('sku', $sku)->first();
            if ($existing && $existing->quality_type_id !== $quality->id) {
                $sku = $skuBase . $suffix;
            }

            $description = $brandName . ' ' . trim($row['models'] ?? '') . ' | ' . ($row['section'] ?? 'Bearing');

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
                    'name'             => 'Bearing',
                    'description'      => $description,
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
                'supplier'    => 'Douglas & Sons (Pvt) Ltd. — NSK',
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
        $words = preg_split('/\s+/', trim($desc));
        return implode(' ', array_slice($words, 0, 3)) ?: 'Universal';
    }
}
