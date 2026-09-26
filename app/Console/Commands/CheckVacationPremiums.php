<?php

namespace App\Console\Commands;

use App\Actions\Notifications\DispatchNotificationAction;
use App\Models\PayrollPeriod;
use App\Models\PayrollSetting;
use App\Models\User;
use App\Models\VacationPeriod;
use App\Services\Payroll\VacationPeriodService;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class CheckVacationPremiums extends Command
{
    protected $signature = 'payroll:check-vacation-premiums';

    protected $description = 'Notify when a collaborator completes a year of service inside the current payroll period (vacation premium due)';

    public function __construct(
        private readonly DispatchNotificationAction $dispatchNotification,
        private readonly VacationPeriodService $vacationPeriodService,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $settings = PayrollSetting::current();

        if (! $settings->vacation_premium_notice_enabled) {
            $this->info('Vacation premium notices are disabled.');

            return self::SUCCESS;
        }

        $today = CarbonImmutable::today();

        $payrollPeriod = PayrollPeriod::query()
            ->open()
            ->whereDate('start_date', '<=', $today)
            ->whereDate('end_date', '>=', $today)
            ->orderByDesc('start_date')
            ->first();

        if (! $payrollPeriod) {
            $this->info('There is no open payroll period for today.');

            return self::SUCCESS;
        }

        $start = CarbonImmutable::parse($payrollPeriod->start_date->toDateString());
        $end = CarbonImmutable::parse($payrollPeriod->end_date->toDateString());

        $users = User::query()
            ->whereHas('payrollProfile', fn ($query) => $query
                ->where('is_payroll_subject', true)
                ->whereNotNull('hire_date'))
            ->with('payrollProfile')
            ->get();

        $notified = 0;

        foreach ($users as $user) {
            $yearNumber = $this->anniversaryYearInPeriod($user, $start, $end);

            if ($yearNumber === null) {
                continue;
            }

            $period = $this->vacationPeriodService->ensureForYear($user, $yearNumber);

            if (! $period || $period->premium_paid_at) {
                continue;
            }

            if (! $this->shouldNotify($settings, $period)) {
                continue;
            }

            $period->update(['premium_notified_at' => now()]);
            $this->dispatchNotification->vacationPremiumDue($user, $period, $payrollPeriod);
            $notified++;
        }

        $this->info("{$notified} vacation premium notification(s) sent.");
        Log::info("Prima vacacional: {$notified} aviso(s) enviados en el periodo de nómina del {$payrollPeriod->label()}.");

        return self::SUCCESS;
    }

    /**
     * Number of the service year completed inside the payroll period, if any.
     */
    private function anniversaryYearInPeriod(User $user, CarbonImmutable $start, CarbonImmutable $end): ?int
    {
        $hire = CarbonImmutable::parse($user->payrollProfile->hire_date->toDateString());

        for ($year = 1; $year <= 80; $year++) {
            $anniversary = $hire->addYears($year);

            if ($anniversary->greaterThan($end)) {
                return null;
            }

            if ($anniversary->greaterThanOrEqualTo($start)) {
                return $year;
            }
        }

        return null;
    }

    /**
     * "once" sends a single notice per anniversary; "daily" repeats the notice
     * every day of the payroll period until the payment is registered.
     */
    private function shouldNotify(PayrollSetting $settings, VacationPeriod $period): bool
    {
        if ($period->premium_notified_at === null) {
            return true;
        }

        if ($settings->vacation_premium_notice_mode !== PayrollSetting::PREMIUM_NOTICE_DAILY) {
            return false;
        }

        return ! $period->premium_notified_at->isToday();
    }
}
