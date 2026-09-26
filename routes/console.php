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

// Weekly payroll rollover: the Monday–Sunday period closes on Sunday 23:59
// and the current week opens on Monday 00:00 (the same idempotent sync runs
// on both moments and can be repeated any time)
Schedule::command('payroll:close-period')->sundays('23:59');
Schedule::command('payroll:close-period')->mondays('00:00');

// Purge attendance captures past the configured retention window (Mondays 03:00)
Schedule::command('payroll:prune-captures')->weeklyOn(1, '03:00');

// Vacation premium notice: collaborators completing a year of service inside
// the current weekly payroll period (the daily run feeds the "each day" mode)
Schedule::command('payroll:check-vacation-premiums')->dailyAt('07:10');
