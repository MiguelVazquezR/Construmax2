<?php

namespace Tests\Feature\Payroll;

use App\Models\Holiday;
use App\Models\PayrollProfile;
use App\Models\Shift;
use App\Models\ShiftAssignment;
use App\Models\User;
use App\Models\VacationAdjustment;
use App\Models\VacationRequest;
use App\Services\Payroll\VacationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class VacationServiceTest extends TestCase
{
    use RefreshDatabase;

    private VacationService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(VacationService::class);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function employee(string $hireDate, bool $withSchedule = false): User
    {
        $user = User::factory()->create(['is_active' => true]);

        PayrollProfile::create([
            'user_id' => $user->id,
            'hire_date' => $hireDate,
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
                'start_date' => $hireDate,
                'is_active' => true,
            ]);
        }

        return $user;
    }

    public function test_entitlement_table_follows_the_lft(): void
    {
        $expected = [
            1 => 12, 2 => 14, 3 => 16, 4 => 18, 5 => 20,
            6 => 22, 10 => 22, 11 => 24, 15 => 24, 16 => 26,
            20 => 26, 21 => 28, 26 => 30, 31 => 32,
        ];

        foreach ($expected as $year => $days) {
            $this->assertSame($days, $this->service->entitledDaysForYearOfService($year), "Season {$year}");
        }
    }

    public function test_current_season_accrues_weekly(): void
    {
        Carbon::setTestNow('2026-07-01');

        $user = $this->employee('2026-01-01');
        $balance = $this->service->balanceFor($user);

        // 25 completed weeks of a 12-day season: 12 * 25 / 52
        $this->assertSame(1, $balance['current_season']);
        $this->assertSame(5.77, $balance['seasons'][0]['accrued']);
        $this->assertSame(5.77, $balance['available_days']);
    }

    public function test_approved_requests_consume_days_fifo(): void
    {
        Carbon::setTestNow('2026-07-01');

        $user = $this->employee('2024-01-01');

        VacationRequest::create([
            'user_id' => $user->id,
            'start_date' => '2026-07-10',
            'end_date' => '2026-07-31',
            'days' => 15,
            'status' => VacationRequest::STATUS_APPROVED,
        ]);

        $balance = $this->service->balanceFor($user);

        // Season 1 (2024) had 12 days; the next 3 consume season 2 (2025).
        $this->assertSame(12.0, $balance['seasons'][0]['taken']);
        $this->assertSame(3.0, $balance['seasons'][1]['taken']);
        $this->assertSame(0.0, $balance['seasons'][2]['taken']);
        $this->assertSame(15.0, $balance['taken_days']);
    }

    public function test_unused_days_expire_after_the_carryover_window(): void
    {
        Carbon::setTestNow('2026-07-01');

        $user = $this->employee('2020-01-01');
        $balance = $this->service->balanceFor($user);

        // Season 5 (2024, 20 days by seniority) ended 2024-12-31; its
        // carryover window (18 months) closed on 2026-06-30.
        $this->assertSame(20.0, $balance['seasons'][4]['expired']);

        // Season 6 (2025) expires 2027-06-30: still available.
        $this->assertSame(0.0, $balance['seasons'][5]['expired']);
        $this->assertSame(22.0, $balance['seasons'][5]['available']);
    }

    public function test_working_days_exclude_rest_days_and_holidays(): void
    {
        $user = $this->employee('2024-01-01', true);

        Holiday::create([
            'date' => '2026-09-16',
            'name' => 'Día de la independencia',
            'year' => 2026,
        ]);

        // Monday 14th to Sunday 20th: 5 workdays minus the holiday on Wednesday.
        $days = $this->service->workingDaysFor(
            $user,
            Carbon::parse('2026-09-14'),
            Carbon::parse('2026-09-20'),
        );

        $this->assertSame(4.0, $days);
    }

    public function test_validate_request_rejects_when_the_balance_is_below_the_minimum(): void
    {
        Carbon::setTestNow('2026-01-05');

        $user = $this->employee('2026-01-01');

        try {
            $this->service->validateRequest($user, '2026-01-05', '2026-01-06');
            $this->fail('Expected a ValidationException.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('balance', $exception->errors());
        }
    }

    public function test_validate_request_rejects_overlapping_requests(): void
    {
        Carbon::setTestNow('2026-07-01');

        $user = $this->employee('2024-01-01');

        VacationRequest::create([
            'user_id' => $user->id,
            'start_date' => '2026-07-10',
            'end_date' => '2026-07-11',
            'days' => 2,
            'status' => VacationRequest::STATUS_PENDING,
        ]);

        try {
            $this->service->validateRequest($user, '2026-07-11', '2026-07-12');
            $this->fail('Expected a ValidationException.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('start_date', $exception->errors());
        }
    }

    public function test_validate_request_returns_the_working_days(): void
    {
        Carbon::setTestNow('2026-07-01');

        $user = $this->employee('2024-01-01', true);

        $days = $this->service->validateRequest($user, '2026-09-14', '2026-09-18');

        $this->assertSame(5.0, $days);
    }

    // --- Manual movements of the balance (initial balance, granted days, adjustments) ---

    public function test_manual_movements_increase_the_available_balance(): void
    {
        Carbon::setTestNow('2026-02-01');

        $user = $this->employee('2026-01-01');

        VacationAdjustment::create([
            'user_id' => $user->id,
            'type' => VacationAdjustment::TYPE_INITIAL,
            'days' => 10,
        ]);

        $balance = $this->service->balanceFor($user);

        // 4 completed weeks of a 12-day season: 0.92 accrued + 10 granted.
        $this->assertSame(0.92, $balance['seasons'][0]['accrued']);
        $this->assertSame(10.0, $balance['adjustment_days']);
        $this->assertSame(10.92, $balance['available_days']);
    }

    public function test_granted_days_and_manual_adjustments_are_signed(): void
    {
        Carbon::setTestNow('2026-02-01');

        $user = $this->employee('2026-01-01');

        VacationAdjustment::create(['user_id' => $user->id, 'type' => VacationAdjustment::TYPE_INITIAL, 'days' => 10]);
        VacationAdjustment::create(['user_id' => $user->id, 'type' => VacationAdjustment::TYPE_GRANT, 'days' => 2]);
        VacationAdjustment::create(['user_id' => $user->id, 'type' => VacationAdjustment::TYPE_ADJUSTMENT, 'days' => -1.5]);

        $balance = $this->service->balanceFor($user);

        $this->assertSame(10.5, $balance['adjustment_days']);
        $this->assertSame(11.42, $balance['available_days']);
    }

    public function test_manual_movements_are_consumed_after_every_season(): void
    {
        Carbon::setTestNow('2026-02-01');

        $user = $this->employee('2026-01-01');

        VacationAdjustment::create([
            'user_id' => $user->id,
            'type' => VacationAdjustment::TYPE_INITIAL,
            'days' => 10,
        ]);

        VacationRequest::create([
            'user_id' => $user->id,
            'start_date' => '2026-02-02',
            'end_date' => '2026-02-06',
            'days' => 5,
            'status' => VacationRequest::STATUS_APPROVED,
        ]);

        $balance = $this->service->balanceFor($user);

        // The LFT season (0.92) is consumed first; the rest (4.08) comes from the initial balance.
        $this->assertSame(0.92, $balance['taken_days']);
        $this->assertSame(5.92, $balance['adjustment_available_days']);
        $this->assertSame(5.92, $balance['available_days']);
    }

    public function test_manual_movements_are_available_without_a_hire_date(): void
    {
        Carbon::setTestNow('2026-02-01');

        $user = User::factory()->create(['is_active' => true]);

        VacationAdjustment::create(['user_id' => $user->id, 'type' => VacationAdjustment::TYPE_INITIAL, 'days' => 8]);
        VacationAdjustment::create(['user_id' => $user->id, 'type' => VacationAdjustment::TYPE_GRANT, 'days' => 2]);

        VacationRequest::create([
            'user_id' => $user->id,
            'start_date' => '2026-02-02',
            'end_date' => '2026-02-04',
            'days' => 3,
            'status' => VacationRequest::STATUS_APPROVED,
        ]);

        $balance = $this->service->balanceFor($user);

        $this->assertNull($balance['hire_date']);
        $this->assertSame([], $balance['seasons']);
        $this->assertSame(10.0, $balance['adjustment_days']);
        $this->assertSame(7.0, $balance['available_days']);
    }

    public function test_a_negative_adjustment_cannot_leave_the_balance_below_zero(): void
    {
        Carbon::setTestNow('2026-02-01');

        $user = $this->employee('2026-01-01');

        VacationAdjustment::create([
            'user_id' => $user->id,
            'type' => VacationAdjustment::TYPE_ADJUSTMENT,
            'days' => -5,
        ]);

        $balance = $this->service->balanceFor($user);

        $this->assertSame(-5.0, $balance['adjustment_days']);
        $this->assertSame(0.0, $balance['available_days']);
    }
}
