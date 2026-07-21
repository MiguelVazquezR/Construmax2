<?php

namespace App\Console\Commands;

use App\Models\WorkAcceptanceReport;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class MigrateSignaturesToDisk extends Command
{
    protected $signature = 'signatures:migrate-to-disk {--clear-db : Nullify the old base64 signature_data column after migration}';

    protected $description = 'Migrate all existing base64 signatures from the database to storage/app/public/signatures/ files.';

    public function handle(): int
    {
        $reports = WorkAcceptanceReport::whereNotNull('signature_data')
            ->whereNull('signature_path')
            ->get();

        if ($reports->isEmpty()) {
            $this->info('No signatures to migrate.');

            return self::SUCCESS;
        }

        $this->info("Found {$reports->count()} signature(s) to migrate.");
        $bar = $this->output->createProgressBar($reports->count());
        $bar->start();

        $migrated = 0;
        $failed = 0;

        foreach ($reports as $report) {
            try {
                $base64Data = $report->signature_data;

                // Strip data URI prefix if present
                if (str_starts_with($base64Data, 'data:image')) {
                    $base64Data = substr($base64Data, strpos($base64Data, ',') + 1);
                }

                $decoded = base64_decode($base64Data, true);

                if ($decoded === false) {
                    $this->warn("\nReport #{$report->id}: invalid base64 data, skipped.");
                    $failed++;
                    $bar->advance();
                    continue;
                }

                $filename = 'signatures/' . $report->id . '_' . ($report->signed_at?->timestamp ?? now()->timestamp) . '.png';

                Storage::disk('public')->put($filename, $decoded);

                $report->update(['signature_path' => $filename]);

                // Optionally clear the base64 column to free DB space
                if ($this->option('clear-db')) {
                    $report->update(['signature_data' => null]);
                }

                $migrated++;
            } catch (\Throwable $e) {
                $this->error("\nReport #{$report->id}: {$e->getMessage()}");
                $failed++;
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        $this->info("Migrated: {$migrated}");
        if ($failed > 0) {
            $this->warn("Failed: {$failed}");
        }

        return self::SUCCESS;
    }
}
