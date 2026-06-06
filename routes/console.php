<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Check for overdue invoices daily at 7am (server cron triggers artisan schedule:run)
Schedule::command('notifications:check-overdue-invoices')->dailyAt('07:00');
