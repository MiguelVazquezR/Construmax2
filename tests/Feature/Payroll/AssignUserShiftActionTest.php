<?php

namespace Tests\Feature\Payroll;

use App\Actions\Payroll\AssignUserShiftAction;
use App\Models\PayrollProfile;
use App\Models\Shift;
use App\Models\ShiftAssignment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class AssignUserShiftActionTest extends TestCase
{
    use RefreshDatabase;

    private function makeShift(string $name): Shift
    {
        return Shift::create([
            'name' => $name,
            'type' => Shift::TYPE_FIXED,
            'start_time' => '09:00',
            'end_time' => '18:00',
            'meal_minutes' => 60,
            'is_meal_paid' => false,
            'days' => [1, 2, 3, 4, 5],
            'is_active' => true,
        ]);
    }

    public function test_it_creates_the_assignment_from_the_hire_date(): void
    {
        $user = User::factory()->create(['is_active' => true]);

        PayrollProfile::create([
            'user_id' => $user->id,
            'hire_date' => '2026-09-01',
            'is_attendance_subject' => true,
        ]);

        $shift = $this->makeShift('Matutino');

        $assignment = app(AssignUserShiftAction::class)->execute($user, $shift->id);

        $this->assertNotNull($assignment);
        $this->assertSame($shift->id, $assignment->shift_id);
        $this->assertSame(ShiftAssignment::TYPE_FIXED, $assignment->type);
        $this->assertSame('2026-09-01', $assignment->start_date->toDateString());
        $this->assertTrue($assignment->is_active);
    }

    public function test_it_replaces_the_current_individual_assignment(): void
    {
        $user = User::factory()->create(['is_active' => true]);
        $morning = $this->makeShift('Matutino');
        $evening = $this->makeShift('Vespertino');

        $existing = ShiftAssignment::create([
            'user_id' => $user->id,
            'type' => ShiftAssignment::TYPE_FIXED,
            'shift_id' => $morning->id,
            'start_date' => '2026-09-01',
            'is_active' => true,
        ]);

        $assignment = app(AssignUserShiftAction::class)->execute($user, $evening->id);

        $this->assertNotNull($assignment);
        $this->assertSame($existing->id, $assignment->id);
        $this->assertSame($evening->id, $assignment->fresh()->shift_id);
        $this->assertDatabaseCount('shift_assignments', 1);
    }

    public function test_it_ignores_missing_or_unknown_shifts(): void
    {
        $user = User::factory()->create(['is_active' => true]);

        $action = app(AssignUserShiftAction::class);

        $this->assertNull($action->execute($user, null));
        $this->assertNull($action->execute($user, 999));
        $this->assertDatabaseCount('shift_assignments', 0);
    }

    public function test_the_form_submission_requires_the_payroll_permission(): void
    {
        Permission::create([
            'name' => 'payroll.profiles.manage',
            'guard_name' => 'web',
            'category' => 'Nómina',
            'description' => 'Manage payroll profiles',
        ]);

        $user = User::factory()->create(['is_active' => true]);
        $shift = $this->makeShift('Matutino');

        $request = Request::create('/', 'POST');
        $request->setUserResolver(fn () => $user);

        $action = app(AssignUserShiftAction::class);

        // Without the permission the field of the form is ignored.
        $this->assertNull($action->executeFromForm($request, $user, ['shift_id' => $shift->id]));
        $this->assertDatabaseCount('shift_assignments', 0);

        // The field was not submitted (technician/user forms always send it, but be safe).
        $this->assertNull($action->executeFromForm($request, $user, []));

        $user->givePermissionTo('payroll.profiles.manage');

        $this->assertNotNull($action->executeFromForm($request, $user, ['shift_id' => $shift->id]));
        $this->assertDatabaseCount('shift_assignments', 1);
    }
}
