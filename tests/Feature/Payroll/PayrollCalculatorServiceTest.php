<?php

namespace Tests\Feature\Payroll;

use App\Models\AttendanceLog;
use App\Models\Holiday;
use App\Models\Incident;
use App\Models\PayrollAdjustment;
use App\Models\PayrollPeriod;
use App\Models\PayrollProfile;
use App\Models\PayrollSetting;
use App\Models\Shift;
use App\Models\ShiftAssignment;
use App\Models\User;
use App\Services\Payroll\PayrollCalculatorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PayrollCalculatorServiceTest extends TestCase
{
    use RefreshDatabase;

    private PayrollCalculatorService $calculator;

    private PayrollPeriod $period;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->calculator = app(PayrollCalculatorService::class);

        // Monday to Sunday week, fully in the past relative to the suite clock.
        $this->period = PayrollPeriod::create([
            'type' => PayrollPeriod::TYPE_WEEKLY,
            'start_date' => '2026-09-07',
            'end_date' => '2026-09-13',
            'status' => PayrollPeriod::STATUS_OPEN,
        ]);

        $this->user = $this->makeEmployee('2024-01-01', 400);
    }

    private function makeEmployee(string $hireDate, float $salary, bool $withSchedule = true): User
    {
        $user = User::factory()->create(['is_active' => true]);

        PayrollProfile::create([
            'user_id' => $user->id,
            'hire_date' => $hireDate,
            'daily_salary' => $salary,
            'daily_hours' => 8,
            'is_payroll_subject' => true,
            'is_attendance_subject' => true,
        ]);

        if ($withSchedule) {
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
        }

        return $user;
    }

    private function punch(User $user, string $type, string $datetime): void
    {
        AttendanceLog::create([
            'user_id' => $user->id,
            'type' => $type,
            'punched_at' => $datetime,
            'source' => AttendanceLog::SOURCE_KIOSK,
            'identifier_method' => AttendanceLog::IDENTIFIER_PIN,
        ]);
    }

    /**
     * Regular day: 08:00 → 17:00 with an unpaid lunch from 13:00 to 14:00.
     */
    private function regularDay(User $user, string $date): void
    {
        $this->punch($user, AttendanceLog::TYPE_CHECK_IN, $date.' 08:00:00');
        $this->punch($user, AttendanceLog::TYPE_LUNCH_START, $date.' 13:00:00');
        $this->punch($user, AttendanceLog::TYPE_LUNCH_END, $date.' 14:00:00');
        $this->punch($user, AttendanceLog::TYPE_CHECK_OUT, $date.' 17:00:00');
    }

    private function workWeek(User $user): void
    {
        foreach (['2026-09-07', '2026-09-08', '2026-09-09', '2026-09-10', '2026-09-11'] as $date) {
            $this->regularDay($user, $date);
        }
    }

    public function test_regular_week_pays_the_five_worked_days(): void
    {
        $this->workWeek($this->user);

        $result = $this->calculator->calculateFor($this->user, $this->period);
        $totals = $result['totals'];

        $this->assertEqualsWithDelta(5.0, $totals['days_paid'], 0.01);
        $this->assertEqualsWithDelta(2000.0, $totals['base_amount'], 0.01);
        $this->assertEqualsWithDelta(0, $totals['overtime_amount'], 0.01);
        $this->assertEqualsWithDelta(0, $totals['late_discount'], 0.01);
        $this->assertEqualsWithDelta(2000.0, $totals['total_net'], 0.01);
    }

    public function test_overtime_splits_at_the_weekly_threshold(): void
    {
        PayrollSetting::current()->update(['overtime_weekly_threshold_hours' => 2]);

        // One worked day with 3 extra hours (8:00 → 20:00 minus 1h lunch).
        $this->punch($this->user, AttendanceLog::TYPE_CHECK_IN, '2026-09-07 08:00:00');
        $this->punch($this->user, AttendanceLog::TYPE_LUNCH_START, '2026-09-07 13:00:00');
        $this->punch($this->user, AttendanceLog::TYPE_LUNCH_END, '2026-09-07 14:00:00');
        $this->punch($this->user, AttendanceLog::TYPE_CHECK_OUT, '2026-09-07 20:00:00');

        $result = $this->calculator->calculateFor($this->user, $this->period);
        $totals = $result['totals'];

        // 180 overtime minutes: 120 double + 60 triple.
        $this->assertSame(120, $totals['overtime_double_minutes']);
        $this->assertSame(60, $totals['overtime_triple_minutes']);

        // Minute rate = 400 / 480; 120 * rate * 2 = 200 and 60 * rate * 3 = 150.
        $this->assertEqualsWithDelta(350.0, $totals['overtime_amount'], 0.01);
    }

    public function test_late_minutes_are_discounted_only_when_configured(): void
    {
        $this->punch($this->user, AttendanceLog::TYPE_CHECK_IN, '2026-09-07 08:30:00');
        $this->punch($this->user, AttendanceLog::TYPE_LUNCH_START, '2026-09-07 13:00:00');
        $this->punch($this->user, AttendanceLog::TYPE_LUNCH_END, '2026-09-07 14:00:00');
        $this->punch($this->user, AttendanceLog::TYPE_CHECK_OUT, '2026-09-07 17:30:00');

        $settings = PayrollSetting::current();
        $settings->update(['late_discount_mode' => PayrollSetting::LATE_TRACK_ONLY]);

        $totals = $this->calculator->calculateFor($this->user, $this->period)['totals'];

        $this->assertSame(30, $totals['late_minutes']);
        $this->assertEqualsWithDelta(0, $totals['late_discount'], 0.01);

        $settings->update(['late_discount_mode' => PayrollSetting::LATE_DEDUCT_MINUTES]);

        $totals = $this->calculator->calculateFor($this->user, $this->period)['totals'];

        // 30 minutes * (400 / 480).
        $this->assertEqualsWithDelta(25.0, $totals['late_discount'], 0.01);
        $this->assertEqualsWithDelta(375.0, $totals['total_net'], 0.01);
    }

    public function test_a_missing_day_is_not_paid(): void
    {
        foreach (['2026-09-07', '2026-09-09', '2026-09-10', '2026-09-11'] as $date) {
            $this->regularDay($this->user, $date);
        }

        $totals = $this->calculator->calculateFor($this->user, $this->period)['totals'];

        $this->assertEqualsWithDelta(4.0, $totals['days_paid'], 0.01);
        $this->assertEqualsWithDelta(1.0, $totals['unpaid_days'], 0.01);
        $this->assertEqualsWithDelta(1600.0, $totals['base_amount'], 0.01);
    }

    public function test_vacations_and_medical_leaves_use_their_pay_fraction(): void
    {
        PayrollSetting::current()->update([
            'incapacity_paid' => true,
            'incapacity_pay_percentage' => 60,
        ]);

        Incident::create([
            'user_id' => $this->user->id,
            'type' => Incident::TYPE_MEDICAL_LEAVE,
            'start_date' => '2026-09-07',
            'end_date' => '2026-09-07',
            'days' => 1,
            'status' => Incident::STATUS_APPROVED,
        ]);

        Incident::create([
            'user_id' => $this->user->id,
            'type' => Incident::TYPE_VACATION,
            'start_date' => '2026-09-08',
            'end_date' => '2026-09-11',
            'days' => 4,
            'status' => Incident::STATUS_APPROVED,
        ]);

        $totals = $this->calculator->calculateFor($this->user, $this->period)['totals'];

        // 0.6 (medical) + 4 (vacation) = 4.6 paid days.
        $this->assertEqualsWithDelta(4.6, $totals['days_paid'], 0.01);
        $this->assertEqualsWithDelta(4.0, $totals['vacation_days'], 0.01);
        $this->assertEqualsWithDelta(0.6, $totals['incapacity_days'], 0.01);
        $this->assertEqualsWithDelta(240.0, $totals['incapacity_amount'], 0.01);
        $this->assertEqualsWithDelta(0.4, $totals['unpaid_days'], 0.01);
    }

    public function test_a_worked_holiday_gets_the_extra_day(): void
    {
        Holiday::create([
            'date' => '2026-09-09',
            'name' => 'Descanso obligatorio de prueba',
            'year' => 2026,
        ]);

        foreach (['2026-09-07', '2026-09-08', '2026-09-10', '2026-09-11'] as $date) {
            $this->regularDay($this->user, $date);
        }

        $this->regularDay($this->user, '2026-09-09');

        $totals = $this->calculator->calculateFor($this->user, $this->period)['totals'];

        $this->assertEqualsWithDelta(5.0, $totals['days_paid'], 0.01);
        $this->assertEqualsWithDelta(1.0, $totals['holiday_days'], 0.01);
        $this->assertEqualsWithDelta(800.0, $totals['holiday_amount'], 0.01);
        $this->assertEqualsWithDelta(2800.0, $totals['total_gross'], 0.01);
        $this->assertEqualsWithDelta(0, $totals['overtime_amount'], 0.01);
    }

    public function test_adjustments_are_included_in_the_totals(): void
    {
        $this->workWeek($this->user);

        PayrollAdjustment::create([
            'payroll_period_id' => $this->period->id,
            'user_id' => $this->user->id,
            'type' => PayrollAdjustment::TYPE_EARNING,
            'concept' => 'Bono de productividad',
            'amount' => 500,
        ]);

        PayrollAdjustment::create([
            'payroll_period_id' => $this->period->id,
            'user_id' => $this->user->id,
            'type' => PayrollAdjustment::TYPE_DEDUCTION,
            'concept' => 'Préstamo',
            'amount' => 150,
        ]);

        $totals = $this->calculator->calculateFor($this->user, $this->period)['totals'];

        $this->assertEqualsWithDelta(2500.0, $totals['total_gross'], 0.01);
        $this->assertEqualsWithDelta(150.0, $totals['total_deductions'], 0.01);
        $this->assertEqualsWithDelta(2350.0, $totals['total_net'], 0.01);
    }

    public function test_days_without_a_schedule_pay_the_worked_fraction(): void
    {
        $other = $this->makeEmployee('2024-01-01', 400, withSchedule: false);

        $this->punch($other, AttendanceLog::TYPE_CHECK_IN, '2026-09-07 09:00:00');
        $this->punch($other, AttendanceLog::TYPE_CHECK_OUT, '2026-09-07 13:00:00');

        $totals = $this->calculator->calculateFor($other, $this->period)['totals'];

        $this->assertEqualsWithDelta(0.5, $totals['days_worked'], 0.01);
        $this->assertEqualsWithDelta(0.5, $totals['days_paid'], 0.01);
        $this->assertEqualsWithDelta(200.0, $totals['total_net'], 0.01);
        $this->assertEqualsWithDelta(0, $totals['overtime_amount'], 0.01);
    }

    public function test_payslip_lines_are_built_for_the_regular_week(): void
    {
        $this->workWeek($this->user);

        $result = $this->calculator->calculateFor($this->user, $this->period);
        $lines = $result['lines'];

        $this->assertCount(1, $lines);
        $this->assertSame('Sueldo', $lines[0]['concept']);
        $this->assertSame('earning', $lines[0]['type']);
        $this->assertEqualsWithDelta(2000.0, (float) $lines[0]['amount'], 0.01);
    }
}
