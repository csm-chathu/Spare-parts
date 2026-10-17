<?php

namespace App\Console\Commands;

use App\Models\PdfImportLog;
use Illuminate\Console\Command;

class PdfImportChecklist extends Command
{
    protected $signature = 'import:checklist';
    protected $description = 'Show checklist of all PDF files in the pdfs/ folder and their import status';

    public function handle(): int
    {
        $pdfDir = base_path('pdfs');

        if (!is_dir($pdfDir)) {
            $this->error("pdfs/ directory not found.");
            return 1;
        }

        $files = glob($pdfDir . DIRECTORY_SEPARATOR . '*.pdf');
        $files = array_map('basename', $files ?: []);

        // Merge with any DB records for files no longer on disk
        $dbFiles = PdfImportLog::pluck('filename')->toArray();
        $allFiles = array_unique(array_merge($files, $dbFiles));
        sort($allFiles);

        if (empty($allFiles)) {
            $this->warn("No PDF files found in pdfs/ and no import history.");
            return 0;
        }

        $rows = [];
        foreach ($allFiles as $filename) {
            $log    = PdfImportLog::where('filename', $filename)->latest()->first();
            $onDisk = in_array($filename, $files);

            $status = match(true) {
                !$onDisk && !$log             => 'missing',
                !$log                         => 'not imported',
                $log->status === 'imported'   => 'imported',
                $log->status === 'extracted'  => 'extracted',
                $log->status === 'failed'     => 'failed',
                default                       => 'pending',
            };

            $statusLabel = match($status) {
                'imported'     => '<fg=green>✓ imported</>',
                'extracted'    => '<fg=yellow>~ extracted</>',
                'failed'       => '<fg=red>✗ failed</>',
                'not imported' => '<fg=cyan>○ not imported</>',
                'missing'      => '<fg=gray>? missing</>',
                default        => $status,
            };

            $rows[] = [
                $filename,
                $log?->supplier ?? '-',
                $statusLabel,
                $log?->imported ? number_format($log->imported) : '-',
                $log?->imported_at?->format('Y-m-d H:i') ?? '-',
                $log?->notes ?? '-',
            ];
        }

        $this->table(
            ['File', 'Supplier', 'Status', 'Imported', 'Date', 'Notes'],
            $rows
        );

        $done    = collect($rows)->filter(fn($r) => str_contains($r[2], '✓ imported'))->count();
        $pending = count($rows) - $done;
        $this->line('');
        $this->info("Total: " . count($rows) . "  |  Done: {$done}  |  Pending: {$pending}");

        return 0;
    }
}
