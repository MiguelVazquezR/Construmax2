<?php

namespace Tests\Feature\Payroll;

use App\Models\NotificationSetting;
use App\Models\PayrollPeriod;
use App\Models\PayrollProfile;
use App\Models\PayrollSetting;
use App\Models\User;
use App\Models\VacationPeriod;
use App\Notifications\VacationPremiumDue;
use App\Services\Payroll\VacationPeriodService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class CheckVacationPremiumsTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    /**
     * Open weekly payroll period (Mon 2026-08-03 — Sun 2026-08-09).
     */
    private function openPeriod(): PayrollPeriod
    {
        return PayrollPeriod::create([
            'type' => PayrollPeriod::TYPE_WEEKLY,
            'start_date' => '2026-08-03',
            'end_date' => '2026-08-09',
            'status' => PayrollPeriod::STATUS_OPEN,
        ]);
    }

    private function employee(string $hireDate): User
    {
        $user = User::factory()->create(['is_active' => true]);

        PayrollProfile::create([
            'user_id' => $user->id,
            'hire_date' => $hireDate,
            'is_payroll_subject' => true,
        ]);

        return $user;
    }

    private function subscriber(): User
    {
        $user = User::factory()->create(['is_active' => true]);

        NotificationSetting::create([
            'notification_type' => 'payroll.vacation-premium',
            'user_id' => $user->id,
            'is_active' => true,
        ]);

        return $user;
    }

    public function test_notifies_subscribers_when_the_anniversary_falls_in_the_open_period(): void
    {
        Carbon::setTestNow('2026-08-03');

        $this->openPeriod();
        $employee = $this->employee('2024-08-05');
        $subscriber = $this->subscriber();

        Notification::fake();

        $this->artisan('payroll:check-vacation-premiums')->assertSuccessful();

        Notification::assertSentTo($subscriber, VacationPremiumDue::class, function (VacationPremiumDue $notification) use ($employee) {
            return $notification->collaborator->is($employee)
                && $notification->vacationPeriod->year_number === 2;
        });

        $this->assertNotNull(VacationPeriod::query()->where('year_number', 2)->first()->premium_notified_at);
    }

    public function test_a_single_notice_is_sent_per_anniversary(): void
    {
        Carbon::setTestNow('2026-08-03');

        $this->openPeriod();
        $this->employee('2024-08-05');
        $subscriber = $this->subscriber();

        Notification::fake();

        $this->artisan('payroll:check-vacation-premiums')->assertSuccessful();

        Carbon::setTestNow('2026-08-04');

        $this->artisan('payroll:check-vacation-premiums')->assertSuccessful();

        Notification::assertSentToTimes($subscriber, VacationPremiumDue::class, 1);
    }

    public function test_daily_mode_repeats_the_notice_each_day_of_the_period(): void
    {
        Carbon::setTestNow('2026-08-03');

        $this->openPeriod();
        PayrollSetting::current()->update(['vacation_premium_notice_mode' => PayrollSetting::PREMIUM_NOTICE_DAILY]);
        $this->employee('2024-08-05');
        $subscriber = $this->subscriber();

        Notification::fake();

        // Running the command twice on the same day sends a single notice.
        $this->artisan('payroll:check-vacation-premiums')->assertSuccessful();
        $this->artisan('payroll:check-vacation-premiums')->assertSuccessful();

        Carbon::setTestNow('2026-08-04');

        $this->artisan('payroll:check-vacation-premiums')->assertSuccessful();

        Notification::assertSentToTimes($subscriber, VacationPremiumDue::class, 2);
    }

    public function test_paid_premiums_and_deleted_periods_are_skipped(): void
    {
        Carbon::setTestNow('2026-08-03');

        $this->openPeriod();

        $paid = $this->employee('2024-08-05');
        app(VacationPeriodService::class)->syncFor($paid);
        VacationPeriod::query()
            ->forUser($paid->id)
            ->where('year_number', 2)
            ->first()
            ->update(['premium_paid_at' => '2026-08-01']);

        $deleted = $this->employee('2023-08-05');
        app(VacationPeriodService::class)->syncFor($deleted);
        VacationPeriod::query()
            ->forUser($deleted->id)
            ->where('year_number', 3)
            ->first()
            ->delete();

        $this->subscriber();

        Notification::fake();

        $this->artisan('payroll:check-vacation-premiums')->assertSuccessful();

        Notification::assertNothingSent();
    }

    public function test_a_period_that_does_not_contain_today_does_not_notify(): void
    {
        // The period was opened early: the notice must wait for its week.
        Carbon::setTestNow('2026-07-27');

        $this->openPeriod();
        $this->employee('2024-08-05');
        $this->subscriber();

        Notification::fake();

        $this->artisan('payroll:check-vacation-premiums')->assertSuccessful();

        Notification::assertNothingSent();
    }

    public function test_does_nothing_when_the_notices_are_disabled(): void
    {
        Carbon::setTestNow('2026-08-03');

        $this->openPeriod();
        PayrollSetting::current()->update(['vacation_premium_notice_enabled' => false]);
        $this->employee('2024-08-05');
        $this->subscriber();

        Notification::fake();

        $this->artisan('payroll:check-vacation-premiums')->assertSuccessful();

        Notification::assertNothingSent();
    }

    public function test_does_nothing_without_an_open_payroll_period(): void
    {
        Carbon::setTestNow('2026-08-03');

        $this->employee('2024-08-05');
        $this->subscriber();

        Notification::fake();

        $this->artisan('payroll:check-vacation-premiums')->assertSuccessful();

        Notification::assertNothingSent();
    }
}
