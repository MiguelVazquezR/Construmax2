<?php

namespace Tests\Feature\Payroll;

use App\Models\Shift;
use App\Models\ShiftAssignment;
use App\Models\User;
use App\Services\Payroll\ScheduleResolverService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ScheduleResolverServiceTest extends TestCase
{
    use RefreshDatabase;

    private ScheduleResolverService $resolver;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->resolver = app(ScheduleResolverService::class);
        $this->user = User::factory()->create(['is_active' => true]);
    }

    private function shift(string $name, array $overrides = []): Shift
    {
        return Shift::create(array_merge([
            'name' => $name,
            'type' => 'fixed',
            'start_time' => '08:00',
            'end_time' => '17:00',
            'meal_minutes' => 60,
            'is_meal_paid' => false,
            'days' => [1, 2, 3, 4, 5],
            'is_active' => true,
        ], $overrides));
    }

    private function assignment(array $attributes): ShiftAssignment
    {
        return ShiftAssignment::create(array_merge([
            'type' => ShiftAssignment::TYPE_FIXED,
            'start_date' => '2026-09-01',
            'is_active' => true,
        ], $attributes));
    }

    public function test_individual_assignment_resolves_the_shift_on_workdays(): void
    {
        $shift = $this->shift('Matutino');

        $this->assignment([
            'user_id' => $this->user->id,
            'shift_id' => $shift->id,
        ]);

        $schedule = $this->resolver->resolveFor($this->user, Carbon::parse('2026-09-14'));

        $this->assertNotNull($schedule);
        $this->assertSame('Matutino', $schedule->shift->name);
        $this->assertTrue($schedule->isWorkday());
        $this->assertSame(480, $schedule->expectedDailyMinutes());
    }

    public function test_assignment_outside_its_date_range_does_not_apply(): void
    {
        $shift = $this->shift('Matutino');

        $this->assignment([
            'user_id' => $this->user->id,
            'shift_id' => $shift->id,
            'start_date' => '2026-10-01',
        ]);

        $this->assertNull($this->resolver->resolveFor($this->user, Carbon::parse('2026-09-14')));
    }

    public function test_department_assignment_is_used_without_an_individual_one(): void
    {
        $this->user->employee()->create([
            'department' => 'Obras',
            'position' => 'Supervisor',
            'phone' => '3311111111',
        ]);

        $shift = $this->shift('Obra completa');

        $this->assignment([
            'department' => 'Obras',
            'shift_id' => $shift->id,
        ]);

        $schedule = $this->resolver->resolveFor($this->user, Carbon::parse('2026-09-14'));

        $this->assertNotNull($schedule);
        $this->assertSame('Obra completa', $schedule->shift->name);
    }

    public function test_individual_assignment_overrides_the_department_one(): void
    {
        $this->user->employee()->create([
            'department' => 'Obras',
            'position' => 'Supervisor',
            'phone' => '3311111111',
        ]);

        $departmentShift = $this->shift('Obra completa');
        $individualShift = $this->shift('Matutino');

        $this->assignment(['department' => 'Obras', 'shift_id' => $departmentShift->id]);
        $this->assignment(['user_id' => $this->user->id, 'shift_id' => $individualShift->id]);

        $schedule = $this->resolver->resolveFor($this->user, Carbon::parse('2026-09-14'));

        $this->assertNotNull($schedule);
        $this->assertSame('Matutino', $schedule->shift->name);
    }

    public function test_rotation_cycles_week_by_week(): void
    {
        $morning = $this->shift('Matutino');
        $night = $this->shift('Nocturno', ['start_time' => '22:00', 'end_time' => '06:00']);

        $this->assignment([
            'user_id' => $this->user->id,
            'type' => ShiftAssignment::TYPE_ROTATION,
            'rotation' => [$morning->id, $night->id],
            'start_date' => '2026-09-07', // Monday
        ]);

        $monday = Carbon::parse('2026-09-07');
        $saturday = Carbon::parse('2026-09-12');
        $nextMonday = Carbon::parse('2026-09-14');
        $thirdMonday = Carbon::parse('2026-09-21');

        $this->assertSame('Matutino', $this->resolver->resolveFor($this->user, $monday)->shift->name);
        $this->assertSame('Matutino', $this->resolver->resolveFor($this->user, $saturday)->shift->name);
        $this->assertSame('Nocturno', $this->resolver->resolveFor($this->user, $nextMonday)->shift->name);
        $this->assertSame('Matutino', $this->resolver->resolveFor($this->user, $thirdMonday)->shift->name);
    }

    public function test_inactive_shifts_are_not_resolved(): void
    {
        $shift = $this->shift('Matutino', ['is_active' => false]);

        $this->assignment([
            'user_id' => $this->user->id,
            'shift_id' => $shift->id,
        ]);

        $this->assertNull($this->resolver->resolveFor($this->user, Carbon::parse('2026-09-14')));
    }

    public function test_weekly_schedule_grid_returns_seven_days(): void
    {
        $shift = $this->shift('Matutino');

        $this->assignment([
            'user_id' => $this->user->id,
            'shift_id' => $shift->id,
        ]);

        $grid = $this->resolver->weeklyScheduleFor($this->user, Carbon::parse('2026-09-14'));

        $this->assertCount(7, $grid);
        $this->assertTrue($grid[1]->isWorkday());   // Monday
        $this->assertFalse($grid[7]->isWorkday());  // Sunday
    }
}
