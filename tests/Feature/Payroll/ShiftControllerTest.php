<?php

namespace Tests\Feature\Payroll;

use App\Models\Shift;
use App\Models\ShiftAssignment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class ShiftControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Permission::create(['name' => 'payroll.shifts.manage', 'guard_name' => 'web', 'category' => 'Nómina', 'description' => 'Manage shifts']);

        $this->admin = User::factory()->create(['is_active' => true]);
        $this->admin->givePermissionTo('payroll.shifts.manage');
    }

    private function shiftPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Turno matutino',
            'type' => 'fixed',
            'start_time' => '08:00',
            'end_time' => '17:00',
            'meal_minutes' => 60,
            'is_meal_paid' => false,
            'days' => [1, 2, 3, 4, 5],
            'required_daily_hours' => null,
            'late_tolerance_minutes' => null,
            'is_active' => true,
            'description' => 'Horario de oficina',
        ], $overrides);
    }

    public function test_index_renders_the_shifts_page(): void
    {
        Shift::create($this->shiftPayload());

        $this->actingAs($this->admin)
            ->get(route('payroll.shifts.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Payroll/Shifts/Index')
                ->has('shifts', 1)
                ->has('assignments')
                ->has('users')
                ->has('weekDays')
            );
    }

    public function test_index_is_forbidden_without_permission(): void
    {
        $user = User::factory()->create(['is_active' => true]);

        $this->actingAs($user)
            ->get(route('payroll.shifts.index'))
            ->assertForbidden();
    }

    public function test_store_creates_a_fixed_shift(): void
    {
        $this->actingAs($this->admin)
            ->post(route('payroll.shifts.store'), $this->shiftPayload())
            ->assertRedirect()
            ->assertSessionHas('success');

        $shift = Shift::first();

        $this->assertNotNull($shift);
        $this->assertSame('Turno matutino', $shift->name);
        $this->assertSame([1, 2, 3, 4, 5], $shift->days);
        $this->assertSame(480, $shift->expectedDailyMinutes());
    }

    public function test_store_validates_required_fields(): void
    {
        $this->actingAs($this->admin)
            ->post(route('payroll.shifts.store'), [])
            ->assertSessionHasErrors(['name', 'type', 'days', 'meal_minutes']);
    }

    public function test_store_requires_daily_hours_for_flexible_shifts(): void
    {
        $this->actingAs($this->admin)
            ->post(route('payroll.shifts.store'), $this->shiftPayload([
                'type' => 'flexible',
                'start_time' => null,
                'end_time' => null,
                'required_daily_hours' => null,
            ]))
            ->assertSessionHasErrors('required_daily_hours');
    }

    public function test_update_modifies_the_shift(): void
    {
        $shift = Shift::create($this->shiftPayload());

        $this->actingAs($this->admin)
            ->put(route('payroll.shifts.update', $shift), $this->shiftPayload([
                'name' => 'Turno vespertino',
                'start_time' => '14:00',
                'end_time' => '22:00',
            ]))
            ->assertRedirect()
            ->assertSessionHas('success');

        $shift->refresh();

        $this->assertSame('Turno vespertino', $shift->name);
        $this->assertSame('14:00', substr((string) $shift->start_time, 0, 5));
    }

    public function test_destroy_deletes_an_unused_shift(): void
    {
        $shift = Shift::create($this->shiftPayload());

        $this->actingAs($this->admin)
            ->delete(route('payroll.shifts.destroy', $shift))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('shifts', ['id' => $shift->id]);
    }

    public function test_destroy_is_blocked_when_the_shift_is_assigned(): void
    {
        $shift = Shift::create($this->shiftPayload());

        ShiftAssignment::create([
            'user_id' => $this->admin->id,
            'type' => ShiftAssignment::TYPE_FIXED,
            'shift_id' => $shift->id,
            'start_date' => '2026-09-01',
        ]);

        $this->actingAs($this->admin)
            ->delete(route('payroll.shifts.destroy', $shift))
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertDatabaseHas('shifts', ['id' => $shift->id]);
    }

    public function test_store_assignment_creates_a_rotation(): void
    {
        $morning = Shift::create($this->shiftPayload(['name' => 'Matutino']));
        $night = Shift::create($this->shiftPayload(['name' => 'Nocturno', 'start_time' => '22:00', 'end_time' => '06:00']));

        $this->actingAs($this->admin)
            ->post(route('payroll.shift-assignments.store'), [
                'user_id' => $this->admin->id,
                'type' => ShiftAssignment::TYPE_ROTATION,
                'rotation' => [$morning->id, $night->id],
                'start_date' => '2026-09-07',
                'is_active' => true,
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $assignment = ShiftAssignment::first();

        $this->assertNotNull($assignment);
        $this->assertSame([$morning->id, $night->id], $assignment->rotation);
        $this->assertSame(['Matutino', 'Nocturno'], $assignment->rotation_labels);
    }

    public function test_store_assignment_validates_the_rotation_size(): void
    {
        $shift = Shift::create($this->shiftPayload());

        $this->actingAs($this->admin)
            ->post(route('payroll.shift-assignments.store'), [
                'user_id' => $this->admin->id,
                'type' => ShiftAssignment::TYPE_ROTATION,
                'rotation' => [$shift->id],
                'start_date' => '2026-09-07',
            ])
            ->assertSessionHasErrors('rotation');
    }

    public function test_destroy_assignment_deletes_it(): void
    {
        $shift = Shift::create($this->shiftPayload());
        $assignment = ShiftAssignment::create([
            'user_id' => $this->admin->id,
            'type' => ShiftAssignment::TYPE_FIXED,
            'shift_id' => $shift->id,
            'start_date' => '2026-09-01',
        ]);

        $this->actingAs($this->admin)
            ->delete(route('payroll.shift-assignments.destroy', $assignment))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('shift_assignments', ['id' => $assignment->id]);
    }
}
