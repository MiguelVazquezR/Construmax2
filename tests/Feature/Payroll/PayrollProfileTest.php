<?php

namespace Tests\Feature\Payroll;

use App\Models\PayrollProfile;
use App\Models\Shift;
use App\Models\Technician;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PayrollProfileTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Permission::create(['name' => 'payroll.profiles.manage', 'guard_name' => 'web', 'category' => 'Nómina', 'description' => 'Manage payroll profiles']);
        Permission::create(['name' => 'payroll.remote-attendance.manage', 'guard_name' => 'web', 'category' => 'Nómina', 'description' => 'Manage remote attendance']);
        Role::create(['name' => 'Operator', 'guard_name' => 'web']);

        $this->admin = User::factory()->create(['is_active' => true]);
        $this->admin->givePermissionTo(['payroll.profiles.manage', 'payroll.remote-attendance.manage']);
    }

    private function userPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Nuevo Empleado',
            'email' => 'empleado@test.com',
            'password' => 'Password123!',
            'roles' => ['Operator'],
            'department' => 'Obras',
            'position' => 'Supervisor',
            'phone' => '3312345678',
        ], $overrides);
    }

    private function updatePayload(User $user, array $overrides = []): array
    {
        return array_merge([
            'name' => $user->name,
            'email' => $user->email,
            'password' => '',
            'roles' => ['Operator'],
            'department' => 'Obras',
            'position' => 'Supervisor',
            'phone' => '3312345678',
            'employee_number' => $user->payrollProfile?->employee_number
                ?: 'EMP-9'.str_pad((string) $user->id, 3, '0', STR_PAD_LEFT),
        ], $overrides);
    }

    private function makeShift(): Shift
    {
        return Shift::create([
            'name' => 'Matutino',
            'type' => Shift::TYPE_FIXED,
            'start_time' => '09:00',
            'end_time' => '18:00',
            'meal_minutes' => 60,
            'is_meal_paid' => false,
            'days' => [1, 2, 3, 4, 5],
            'is_active' => true,
        ]);
    }

    // --- Users ---

    public function test_user_store_creates_the_payroll_profile(): void
    {
        $shift = $this->makeShift();

        $this->actingAs($this->admin)
            ->post(route('users.store'), $this->userPayload([
                'employee_number' => 'EMP-0001',
                'hire_date' => '2024-03-01',
                'daily_salary' => 550.50,
                'daily_hours' => 8,
                'is_payroll_subject' => true,
                'is_attendance_subject' => true,
                'can_remote_attendance' => true,
                'shift_id' => $shift->id,
            ]))
            ->assertRedirect(route('users.index'));

        $profile = PayrollProfile::first();

        $this->assertNotNull($profile);
        $this->assertSame('EMP-0001', $profile->employee_number);
        $this->assertEquals(550.50, (float) $profile->daily_salary);
        $this->assertSame('2024-03-01', $profile->hire_date->toDateString());
        $this->assertTrue($profile->is_payroll_subject);
        $this->assertTrue($profile->is_attendance_subject);
        $this->assertTrue($profile->can_remote_attendance);

        // Subject to payroll: the schedule was assigned and the hours follow it.
        $this->assertDatabaseHas('shift_assignments', [
            'user_id' => $profile->user_id,
            'shift_id' => $shift->id,
        ]);
        $this->assertEquals(8.0, (float) $profile->daily_hours);
    }

    public function test_user_subject_to_payroll_requires_a_schedule(): void
    {
        $this->actingAs($this->admin)
            ->post(route('users.store'), $this->userPayload([
                'employee_number' => 'EMP-0001',
                'is_payroll_subject' => true,
            ]))
            ->assertSessionHasErrors('shift_id');

        $this->assertDatabaseCount('payroll_profiles', 0);
    }

    public function test_user_store_requires_the_employee_number(): void
    {
        $this->actingAs($this->admin)
            ->post(route('users.store'), $this->userPayload())
            ->assertSessionHasErrors('employee_number');

        $this->assertDatabaseCount('payroll_profiles', 0);
    }

    public function test_employee_number_must_be_unique(): void
    {
        $existing = User::factory()->create(['is_active' => true]);
        PayrollProfile::create([
            'user_id' => $existing->id,
            'employee_number' => 'EMP-0001',
        ]);

        $this->actingAs($this->admin)
            ->post(route('users.store'), $this->userPayload([
                'employee_number' => 'EMP-0001',
            ]))
            ->assertSessionHasErrors('employee_number');

        $this->assertDatabaseCount('payroll_profiles', 1);
    }

    public function test_user_store_ignores_payroll_fields_without_permission(): void
    {
        $basicUser = User::factory()->create(['is_active' => true]);

        $this->actingAs($basicUser)
            ->post(route('users.store'), $this->userPayload([
                'email' => 'sinpermiso@test.com',
                'is_payroll_subject' => true,
                'daily_salary' => 100,
            ]))
            ->assertRedirect(route('users.index'));

        $this->assertDatabaseCount('payroll_profiles', 0);
    }

    public function test_user_update_syncs_the_payroll_profile(): void
    {
        $user = User::factory()->create(['is_active' => true]);
        $shift = $this->makeShift();

        $this->actingAs($this->admin)
            ->put(route('users.update', $user), $this->updatePayload($user, [
                'hire_date' => '2025-01-15',
                'daily_salary' => 600,
                'is_attendance_subject' => true,
                'shift_id' => $shift->id,
            ]))
            ->assertRedirect(route('users.index'));

        $profile = PayrollProfile::where('user_id', $user->id)->first();

        $this->assertNotNull($profile);
        $this->assertSame('2025-01-15', $profile->hire_date->toDateString());
        $this->assertEquals(600, (float) $profile->daily_salary);
        $this->assertTrue($profile->is_attendance_subject);
        // Registering attendance requires being a payroll subject.
        $this->assertTrue($profile->is_payroll_subject);
    }

    public function test_user_attendance_requires_a_schedule(): void
    {
        $user = User::factory()->create(['is_active' => true]);

        $this->actingAs($this->admin)
            ->put(route('users.update', $user), $this->updatePayload($user, [
                'is_attendance_subject' => true,
            ]))
            ->assertSessionHasErrors('shift_id');
    }

    public function test_user_update_saves_the_termination_date(): void
    {
        $user = User::factory()->create(['is_active' => true]);
        $shift = $this->makeShift();

        $this->actingAs($this->admin)
            ->put(route('users.update', $user), $this->updatePayload($user, [
                'hire_date' => '2024-01-01',
                'termination_date' => '2026-09-15',
                'is_payroll_subject' => true,
                'shift_id' => $shift->id,
            ]))
            ->assertRedirect(route('users.index'));

        $profile = PayrollProfile::where('user_id', $user->id)->first();

        $this->assertNotNull($profile);
        $this->assertSame('2026-09-15', $profile->termination_date->toDateString());
    }

    public function test_termination_date_cannot_be_before_the_hire_date(): void
    {
        $user = User::factory()->create(['is_active' => true]);

        $this->actingAs($this->admin)
            ->put(route('users.update', $user), $this->updatePayload($user, [
                'hire_date' => '2024-01-01',
                'termination_date' => '2023-12-31',
            ]))
            ->assertSessionHasErrors('termination_date');
    }

    public function test_user_update_changes_the_employee_number(): void
    {
        $user = User::factory()->create(['is_active' => true]);
        PayrollProfile::create([
            'user_id' => $user->id,
            'employee_number' => 'EMP-0100',
        ]);

        $this->actingAs($this->admin)
            ->put(route('users.update', $user), $this->updatePayload($user, [
                'employee_number' => 'EMP-0101',
            ]))
            ->assertRedirect(route('users.index'));

        $this->assertSame('EMP-0101', $user->payrollProfile->fresh()->employee_number);
    }

    public function test_disabling_attendance_disables_remote_attendance(): void
    {
        $user = User::factory()->create(['is_active' => true]);
        $profile = PayrollProfile::create([
            'user_id' => $user->id,
            'is_attendance_subject' => true,
            'can_remote_attendance' => true,
        ]);

        $this->actingAs($this->admin)
            ->put(route('users.update', $user), $this->updatePayload($user, [
                'is_attendance_subject' => false,
                'can_remote_attendance' => true,
            ]))
            ->assertRedirect(route('users.index'));

        $profile->refresh();

        $this->assertFalse($profile->is_attendance_subject);
        $this->assertFalse($profile->can_remote_attendance);
    }

    public function test_remote_attendance_can_be_managed_with_its_own_permission(): void
    {
        $remoteManager = User::factory()->create(['is_active' => true]);
        $remoteManager->givePermissionTo('payroll.remote-attendance.manage');

        $user = User::factory()->create(['is_active' => true]);
        $profile = PayrollProfile::create([
            'user_id' => $user->id,
            'is_attendance_subject' => true,
            'daily_salary' => 500,
        ]);

        $this->actingAs($remoteManager)
            ->put(route('users.update', $user), $this->updatePayload($user, [
                'is_attendance_subject' => false, // filtered out: no payroll.profiles.manage
                'daily_salary' => 9999,           // filtered out
                'can_remote_attendance' => true,  // allowed by the dedicated permission
            ]))
            ->assertRedirect(route('users.index'));

        $profile->refresh();

        $this->assertTrue($profile->is_attendance_subject);
        $this->assertEquals(500, (float) $profile->daily_salary);
        $this->assertTrue($profile->can_remote_attendance);
    }

    // --- Technicians ---

    public function test_external_technician_cannot_register_attendance(): void
    {
        $this->actingAs($this->admin)
            ->post(route('technicians.store'), [
                'name' => 'Técnico Externo',
                'email' => 'externo@test.com',
                'phone' => '3311112233',
                'is_attendance_subject' => true,
                'can_remote_attendance' => true,
            ])
            ->assertRedirect(route('technicians.index'));

        $technician = Technician::first();
        $profile = PayrollProfile::where('user_id', $technician->user_id)->first();

        $this->assertNotNull($profile);
        $this->assertFalse($profile->is_attendance_subject);
        $this->assertFalse($profile->can_remote_attendance);
        $this->assertFalse($profile->is_payroll_subject);
    }

    public function test_technician_store_creates_the_attendance_profile(): void
    {
        $shift = $this->makeShift();

        $this->actingAs($this->admin)
            ->post(route('technicians.store'), [
                'name' => 'Técnico Interno',
                'email' => 'interno-asistencia@test.com',
                'password' => 'Password123!',
                'roles' => ['Operator'],
                'phone' => '3311112233',
                'is_internal' => true,
                'employee_number' => 'EMP-0200',
                'is_attendance_subject' => true,
                'can_remote_attendance' => true,
                'shift_id' => $shift->id,
            ])
            ->assertRedirect(route('technicians.index'));

        $technician = Technician::first();
        $profile = PayrollProfile::where('user_id', $technician->user_id)->first();

        $this->assertNotNull($profile);
        $this->assertSame('EMP-0200', $profile->employee_number);
        $this->assertTrue($profile->is_attendance_subject);
        $this->assertTrue($profile->can_remote_attendance);
        // Registering attendance requires being a payroll subject.
        $this->assertTrue($profile->is_payroll_subject);
    }

    public function test_technician_update_syncs_the_attendance_profile(): void
    {
        $user = User::factory()->create(['is_active' => true]);
        $technician = Technician::create([
            'user_id' => $user->id,
            'phone' => '3333333333',
            'status' => 'Activo',
        ]);
        $shift = $this->makeShift();

        $this->actingAs($this->admin)
            ->put(route('technicians.update', $technician), [
                'name' => $user->name,
                'email' => $user->email,
                'password' => 'Password123!',
                'roles' => ['Operator'],
                'phone' => '3333333333',
                'status' => 'Activo',
                'is_internal' => true,
                'employee_number' => 'EMP-0300',
                'is_attendance_subject' => true,
                'shift_id' => $shift->id,
            ])
            ->assertRedirect(route('technicians.show', $technician->id));

        $profile = PayrollProfile::where('user_id', $user->id)->first();

        $this->assertNotNull($profile);
        $this->assertSame('EMP-0300', $profile->employee_number);
        $this->assertTrue($profile->is_attendance_subject);
        $this->assertTrue($profile->is_payroll_subject);
    }

    public function test_internal_technician_can_be_a_payroll_subject(): void
    {
        $shift = $this->makeShift();

        $this->actingAs($this->admin)
            ->post(route('technicians.store'), [
                'name' => 'Técnico Interno',
                'email' => 'interno@test.com',
                'password' => 'Password123!',
                'roles' => ['Operator'],
                'phone' => '3322223333',
                'is_internal' => true,
                'employee_number' => 'EMP-0400',
                'hire_date' => '2026-01-15',
                'daily_salary' => 480,
                'daily_hours' => 8,
                'is_payroll_subject' => true,
                'shift_id' => $shift->id,
            ])
            ->assertRedirect(route('technicians.index'));

        $technician = Technician::query()
            ->whereHas('user', fn ($query) => $query->where('email', 'interno@test.com'))
            ->firstOrFail();

        $profile = PayrollProfile::where('user_id', $technician->user_id)->first();

        $this->assertNotNull($profile);
        $this->assertTrue($profile->is_payroll_subject);
        $this->assertSame('2026-01-15', $profile->hire_date->toDateString());
        $this->assertEquals(480, (float) $profile->daily_salary);

        // The schedule was assigned and the hours follow it.
        $this->assertDatabaseHas('shift_assignments', [
            'user_id' => $technician->user_id,
            'shift_id' => $shift->id,
        ]);
        $this->assertEquals(8.0, (float) $profile->daily_hours);

        // The profile shows up in the payroll periods query.
        $this->assertTrue(PayrollProfile::payrollSubjectOn('2026-09-22')->whereKey($profile->id)->exists());
    }

    public function test_internal_technician_subject_to_payroll_requires_a_schedule(): void
    {
        $this->actingAs($this->admin)
            ->post(route('technicians.store'), [
                'name' => 'Técnico Sin Horario',
                'email' => 'sin-horario@test.com',
                'phone' => '3366667777',
                'is_internal' => true,
                'employee_number' => 'EMP-0500',
                'is_payroll_subject' => true,
            ])
            ->assertSessionHasErrors('shift_id');

        $this->assertDatabaseCount('payroll_profiles', 0);
    }

    public function test_external_technician_can_never_be_a_payroll_subject(): void
    {
        $this->actingAs($this->admin)
            ->post(route('technicians.store'), [
                'name' => 'Técnico Externo',
                'email' => 'externo-nomina@test.com',
                'phone' => '3344445555',
                'is_internal' => false,
                'hire_date' => '2026-01-15',
                'daily_salary' => 480,
                'daily_hours' => 8,
                'is_payroll_subject' => true,
            ])
            ->assertRedirect(route('technicians.index'));

        $technician = Technician::query()
            ->whereHas('user', fn ($query) => $query->where('email', 'externo-nomina@test.com'))
            ->firstOrFail();

        $profile = PayrollProfile::where('user_id', $technician->user_id)->first();

        $this->assertNotNull($profile);
        $this->assertFalse($profile->is_payroll_subject);
    }

    public function test_technician_downgraded_to_external_stops_being_a_payroll_subject(): void
    {
        $user = User::factory()->create(['is_active' => true]);
        $technician = Technician::create([
            'user_id' => $user->id,
            'phone' => '3355556666',
            'is_internal' => true,
            'status' => 'Activo',
        ]);
        $profile = PayrollProfile::create([
            'user_id' => $user->id,
            'hire_date' => '2026-01-15',
            'is_payroll_subject' => true,
        ]);

        $this->actingAs($this->admin)
            ->put(route('technicians.update', $technician), [
                'name' => $user->name,
                'email' => $user->email,
                'phone' => '3355556666',
                'status' => 'Activo',
                'is_internal' => false,
                'is_payroll_subject' => true,
            ])
            ->assertRedirect(route('technicians.show', $technician->id));

        $this->assertFalse($profile->fresh()->is_payroll_subject);
    }
}
