<?php

namespace App\Console\Commands;

use App\Services\Payroll\HolidayService;
use Illuminate\Console\Command;

class SyncHolidayYears extends Command
{
    protected $signature = 'payroll:sync-holidays {--year= : Specific year to generate} {--years=2 : How many years to generate from the current one}';

    protected $description = 'Generate the mandatory rest days of the Federal Labor Law for the current and coming years';

    public function handle(HolidayService $holidayService): int
    {
        $years = [];

        if ($this->option('year')) {
            $years[] = (int) $this->option('year');
        } else {
            for ($offset = 0; $offset < (int) $this->option('years'); $offset++) {
                $years[] = (int) now()->addYears($offset)->year;
            }
        }

        foreach ($years as $year) {
            $created = $holidayService->syncYear($year);
            $this->info("Year {$year}: {$created} holidays created.");
        }

        return self::SUCCESS;
    }
}
