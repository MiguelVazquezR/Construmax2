<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Check for overdue invoices daily at 7am (server cron triggers artisan schedule:run)
Schedule::command('notifications:check-overdue-invoices')->dailyAt('07:00');

// Generate next year's mandatory rest days (LFT art. 74) every December 1st
Schedule::command('payroll:sync-holidays')->yearlyOn(12, 1, '02:00');

// Close due payroll periods at 01:00 (the period starts that day per settings)
Schedule::command('payroll:close-period')->dailyAt('01:00');

// Purge attendance captures past the configured retention window (Mondays 03:00)
Schedule::command('payroll:prune-captures')->weeklyOn(1, '03:00');
