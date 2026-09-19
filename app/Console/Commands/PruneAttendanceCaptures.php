<?php

namespace App\Console\Commands;

use App\Models\AttendanceLog;
use App\Models\PayrollSetting;
use Illuminate\Console\Command;

class PruneAttendanceCaptures extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'payroll:prune-captures';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Delete attendance captures older than the configured retention window';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $months = (int) PayrollSetting::current()->attendance_capture_retention_months;

        if ($months < 1) {
            $this->info('The retention window is disabled; nothing to prune.');

            return self::SUCCESS;
        }

        $threshold = now()->subMonthsNoOverflow($months);
        $pruned = 0;

        AttendanceLog::query()
            ->where('punched_at', '<', $threshold)
            ->with('media')
            ->chunkById(200, function ($logs) use (&$pruned) {
                foreach ($logs as $log) {
                    if ($log->hasMedia('capture')) {
                        $log->clearMediaCollection('capture');
                        $pruned++;
                    }
                }
            });

        $this->info("Attendance captures pruned: {$pruned} (older than {$threshold->toDateString()}).");

        return self::SUCCESS;
    }
}
