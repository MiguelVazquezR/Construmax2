<?php

namespace App\Console\Commands;

use App\Models\PayrollPeriod;
use App\Services\Payroll\PayrollPeriodService;
use Illuminate\Console\Command;

class ClosePayrollPeriod extends Command
{
    protected $signature = 'payroll:close-period {--period= : Id of a specific open period to close}';

    protected $description = 'Close due payroll periods: freeze payslips, register the payroll expense and open the next period';

    public function handle(PayrollPeriodService $periodService): int
    {
        if ($this->option('period')) {
            $period = PayrollPeriod::find((int) $this->option('period'));

            if (! $period) {
                $this->error('Payroll period not found.');

                return self::FAILURE;
            }
        } else {
            $period = $periodService->duePeriod();
        }

        if (! $period) {
            $this->info('No payroll period is due for closing.');

            return self::SUCCESS;
        }

        if (! $period->isOpen()) {
            $this->warn('The period is already closed.');

            return self::FAILURE;
        }

        $periodService->close($period);
        $period->refresh();

        $next = $periodService->createNextPeriod($period);

        $this->info("Period {$period->id} closed ({$period->label()}). Net total: {$period->total_net}.");
        $this->info("Next period opened: {$next->label()}.");

        return self::SUCCESS;
    }
}
