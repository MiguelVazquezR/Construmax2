<?php

namespace App\Console\Commands;

use App\Models\PayrollPeriod;
use App\Services\Payroll\PayrollPeriodService;
use Illuminate\Console\Command;

class ClosePayrollPeriod extends Command
{
    protected $signature = 'payroll:close-period {--period= : Id of a specific open period to close}';

    protected $description = 'Weekly payroll rollover: close the Monday–Sunday period that ended and open the current week';

    public function handle(PayrollPeriodService $periodService): int
    {
        if ($this->option('period')) {
            return $this->closeSinglePeriod($periodService, (int) $this->option('period'));
        }

        $result = $periodService->syncAutomatic();

        if ($result['closed']) {
            $this->info("Period {$result['closed']->id} closed ({$result['closed']->label()}). Net total: {$result['closed']->total_net}.");
        }

        if ($result['opened']) {
            $this->info("Current period opened: {$result['opened']->label()}.");
        }

        if (! $result['closed'] && ! $result['opened']) {
            $this->info('Nothing to do: the current week is already covered.');
        }

        return self::SUCCESS;
    }

    private function closeSinglePeriod(PayrollPeriodService $periodService, int $id): int
    {
        $period = PayrollPeriod::find($id);

        if (! $period) {
            $this->error('Payroll period not found.');

            return self::FAILURE;
        }

        if (! $period->isOpen()) {
            $this->warn('The period is already closed.');

            return self::FAILURE;
        }

        $periodService->close($period);
        $period->refresh();

        $this->info("Period {$period->id} closed ({$period->label()}). Net total: {$period->total_net}.");

        return self::SUCCESS;
    }
}
