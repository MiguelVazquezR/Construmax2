<?php

namespace Tests\Feature\Payroll;

use App\Models\AttendanceLog;
use App\Models\Expense;
use App\Models\PayrollPeriod;
use App\Models\PayrollProfile;
use App\Models\Shift;
use App\Models\ShiftAssignment;
use App\Models\User;
use App\Services\Payroll\PayrollPeriodService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PayrollPeriodServiceTest extends TestCase
{
    use RefreshDatabase;

    private PayrollPeriodService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(PayrollPeriodService::class);
        Carbon::setTestNow('2026-09-19');
        Notification::fake();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function makeEmployee(bool $withPunches = true): User
    {
        $user = User::factory()->create(['is_active' => true]);

        PayrollProfile::create([
            'user_id' => $user->id,
            'hire_date' => '2024-01-01',
            'daily_salary' => 400,
            'daily_hours' => 8,
            'is_payroll_subject' => true,
            'is_attendance_subject' => true,
        ]);

        $shift = Shift::create([
            'name' => 'Matutino',
            'type' => 'fixed',
            'start_time' => '08:00',
            'end_time' => '17:00',
            'meal_minutes' => 60,
            'days' => [1, 2, 3, 4, 5],
            'is_active' => true,
        ]);

        ShiftAssignment::create([
            'user_id' => $user->id,
            'type' => ShiftAssignment::TYPE_FIXED,
            'shift_id' => $shift->id,
            'start_date' => '2026-09-01',
            'is_active' => true,
        ]);

        if ($withPunches) {
            AttendanceLog::create([
                'user_id' => $user->id,
                'type' => AttendanceLog::TYPE_CHECK_IN,
                'punched_at' => '2026-09-07 08:00:00',
                'source' => AttendanceLog::SOURCE_KIOSK,
                'identifier_method' => AttendanceLog::IDENTIFIER_PIN,
            ]);

            AttendanceLog::create([
                'user_id' => $user->id,
                'type' => AttendanceLog::TYPE_LUNCH_START,
                'punched_at' => '2026-09-07 13:00:00',
                'source' => AttendanceLog::SOURCE_KIOSK,
                'identifier_method' => AttendanceLog::IDENTIFIER_PIN,
            ]);

            AttendanceLog::create([
                'user_id' => $user->id,
                'type' => AttendanceLog::TYPE_LUNCH_END,
                'punched_at' => '2026-09-07 14:00:00',
                'source' => AttendanceLog::SOURCE_KIOSK,
                'identifier_method' => AttendanceLog::IDENTIFIER_PIN,
            ]);

            AttendanceLog::create([
                'user_id' => $user->id,
                'type' => AttendanceLog::TYPE_CHECK_OUT,
                'punched_at' => '2026-09-07 17:00:00',
                'source' => AttendanceLog::SOURCE_KIOSK,
                'identifier_method' => AttendanceLog::IDENTIFIER_PIN,
            ]);
        }

        return $user;
    }

    private function openPeriod(string $start, string $end): PayrollPeriod
    {
        return PayrollPeriod::create([
            'type' => PayrollPeriod::TYPE_WEEKLY,
            'start_date' => $start,
            'end_date' => $end,
            'status' => PayrollPeriod::STATUS_OPEN,
        ]);
    }

    public function test_open_next_period_opens_the_current_week_when_empty(): void
    {
        $period = $this->service->openNextPeriod();

        $this->assertSame(PayrollPeriod::TYPE_WEEKLY, $period->type);
        $this->assertSame(PayrollPeriod::STATUS_OPEN, $period->status);
        $this->assertSame('2026-09-14', $period->start_date->toDateString());
        $this->assertSame('2026-09-20', $period->end_date->toDateString());
    }

    public function test_open_next_period_starts_after_the_last_week(): void
    {
        $current = $this->openPeriod('2026-09-07', '2026-09-13');

        $next = $this->service->openNextPeriod();

        $this->assertSame('2026-09-14', $next->start_date->toDateString());
        $this->assertSame('2026-09-20', $next->end_date->toDateString());
        $this->assertSame(PayrollPeriod::STATUS_OPEN, $current->fresh()->status);
    }

    public function test_open_next_period_skips_weeks_that_already_exist(): void
    {
        $this->openPeriod('2026-09-07', '2026-09-13');
        $this->openPeriod('2026-09-14', '2026-09-20');

        $next = $this->service->openNextPeriod();

        $this->assertSame('2026-09-21', $next->start_date->toDateString());
        $this->assertSame('2026-09-27', $next->end_date->toDateString());
        $this->assertDatabaseCount('payroll_periods', 3);
    }

    public function test_ensure_current_week_does_not_duplicate_existing_dates(): void
    {
        PayrollPeriod::create([
            'type' => PayrollPeriod::TYPE_WEEKLY,
            'start_date' => '2026-09-14',
            'end_date' => '2026-09-20',
            'status' => PayrollPeriod::STATUS_CLOSED,
        ]);

        $this->assertNull($this->service->ensureCurrentWeek());
        $this->assertDatabaseCount('payroll_periods', 1);
        $this->assertSame(PayrollPeriod::STATUS_CLOSED, PayrollPeriod::query()->first()->status);
    }

    public function test_ensure_current_week_is_idempotent(): void
    {
        $period = $this->service->ensureCurrentWeek();

        $this->assertNotNull($period);
        $this->assertSame('2026-09-14', $period->start_date->toDateString());
        $this->assertSame('2026-09-20', $period->end_date->toDateString());

        $this->assertNull($this->service->ensureCurrentWeek());
        $this->assertDatabaseCount('payroll_periods', 1);
    }

    public function test_sync_closes_the_week_that_ended_and_opens_the_next_monday(): void
    {
        $this->makeEmployee();
        $week = $this->openPeriod('2026-09-07', '2026-09-13');

        $sunday = $this->service->syncAutomatic(CarbonImmutable::parse('2026-09-13 23:59:10'));

        $this->assertSame(PayrollPeriod::STATUS_CLOSED, $week->fresh()->status);
        $this->assertSame($week->id, $sunday['closed']->id);
        $this->assertNull($sunday['opened']);
        $this->assertSame(1, $week->fresh()->payslips()->count());

        $monday = $this->service->syncAutomatic(CarbonImmutable::parse('2026-09-14 00:05:00'));

        $this->assertNull($monday['closed']);
        $this->assertSame('2026-09-14', $monday['opened']->start_date->toDateString());
        $this->assertSame('2026-09-20', $monday['opened']->end_date->toDateString());
        $this->assertSame(PayrollPeriod::STATUS_OPEN, $monday['opened']->status);
    }

    public function test_sync_never_closes_a_reopened_period(): void
    {
        $week = $this->openPeriod('2026-09-07', '2026-09-13');
        $week->update(['reopened_at' => now()]);

        $result = $this->service->syncAutomatic(CarbonImmutable::parse('2026-09-13 23:59:30'));

        $this->assertNull($result['closed']);
        $this->assertSame(PayrollPeriod::STATUS_OPEN, $week->fresh()->status);
    }

    public function test_close_freezes_payslips_and_registers_the_expense(): void
    {
        $this->makeEmployee();
        $period = $this->openPeriod('2026-09-07', '2026-09-13');

        $closed = $this->service->close($period);

        $this->assertSame(PayrollPeriod::STATUS_CLOSED, $closed->status);
        $this->assertEqualsWithDelta(400.0, (float) $closed->total_net, 0.01);
        $this->assertSame(1, $closed->payslips()->count());

        $expense = $closed->expense;

        $this->assertNotNull($expense);
        $this->assertEqualsWithDelta(400.0, (float) $expense->amount, 0.01);
        $this->assertSame(Expense::STATUS_PENDING, $expense->status);
        $this->assertSame($period->id, $expense->payroll_period_id);
    }

    public function test_close_skips_the_expense_when_the_net_is_zero(): void
    {
        $this->makeEmployee(withPunches: false);
        $period = $this->openPeriod('2026-09-07', '2026-09-13');

        $closed = $this->service->close($period);

        $this->assertNull($closed->expense_id);
        $this->assertDatabaseCount('expenses', 0);
    }

    public function test_reopen_reverts_the_close(): void
    {
        $this->makeEmployee();
        $period = $this->openPeriod('2026-09-07', '2026-09-13');

        $closed = $this->service->close($period);
        $expenseId = $closed->expense_id;

        $reopened = $this->service->reopen($closed, User::factory()->create());

        $this->assertSame(PayrollPeriod::STATUS_OPEN, $reopened->status);
        $this->assertNotNull($reopened->reopened_at);
        $this->assertNull($reopened->total_net);
        $this->assertNull($reopened->expense_id);
        $this->assertSame(0, $reopened->payslips()->count());
        $this->assertDatabaseMissing('expenses', ['id' => $expenseId]);
    }

    public function test_reopen_is_blocked_when_the_expense_was_paid(): void
    {
        $this->makeEmployee();
        $period = $this->openPeriod('2026-09-07', '2026-09-13');

        $closed = $this->service->close($period);
        $closed->expense->update(['status' => Expense::STATUS_PAID, 'paid_at' => now()]);

        $this->expectException(ValidationException::class);

        $this->service->reopen($closed->fresh(), User::factory()->create());
    }

    public function test_command_is_a_noop_when_the_current_week_is_already_open(): void
    {
        $period = $this->openPeriod('2026-09-14', '2026-09-20');

        $this->artisan('payroll:close-period')
            ->expectsOutput('Nothing to do: the current week is already covered.')
            ->assertSuccessful();

        $this->assertSame(PayrollPeriod::STATUS_OPEN, $period->fresh()->status);
    }

    public function test_command_closes_the_ended_week_and_opens_the_current_one(): void
    {
        $this->makeEmployee();
        $period = $this->openPeriod('2026-09-07', '2026-09-13');

        $this->artisan('payroll:close-period')->assertSuccessful();

        $this->assertSame(PayrollPeriod::STATUS_CLOSED, $period->fresh()->status);

        $this->assertDatabaseHas('payroll_periods', [
            'start_date' => '2026-09-14 00:00:00',
            'status' => PayrollPeriod::STATUS_OPEN,
        ]);

        $this->assertDatabaseCount('expenses', 1);
    }
}
