<?php

namespace Tests\Feature\Payroll;

use App\Models\PayrollProfile;
use App\Models\Technician;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
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
        ], $overrides);
    }

    // --- Users ---

    public function test_user_store_creates_the_payroll_profile(): void
    {
        $this->actingAs($this->admin)
            ->post(route('users.store'), $this->userPayload([
                'hire_date' => '2024-03-01',
                'daily_salary' => 550.50,
                'daily_hours' => 8,
                'is_payroll_subject' => true,
                'is_attendance_subject' => true,
                'can_remote_attendance' => true,
                'kiosk_pin' => '4321',
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
        $this->assertTrue(Hash::check('4321', $profile->kiosk_pin));
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

        $this->actingAs($this->admin)
            ->put(route('users.update', $user), $this->updatePayload($user, [
                'hire_date' => '2025-01-15',
                'daily_salary' => 600,
                'is_attendance_subject' => true,
            ]))
            ->assertRedirect(route('users.index'));

        $profile = PayrollProfile::where('user_id', $user->id)->first();

        $this->assertNotNull($profile);
        $this->assertSame('2025-01-15', $profile->hire_date->toDateString());
        $this->assertEquals(600, (float) $profile->daily_salary);
        $this->assertTrue($profile->is_attendance_subject);
    }

    public function test_user_update_saves_the_termination_date(): void
    {
        $user = User::factory()->create(['is_active' => true]);

        $this->actingAs($this->admin)
            ->put(route('users.update', $user), $this->updatePayload($user, [
                'hire_date' => '2024-01-01',
                'termination_date' => '2026-09-15',
                'is_payroll_subject' => true,
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

    public function test_kiosk_pin_keeps_its_value_when_left_empty(): void
    {
        $user = User::factory()->create(['is_active' => true]);
        $profile = PayrollProfile::create(['user_id' => $user->id]);

        $this->actingAs($this->admin)
            ->put(route('users.update', $user), $this->updatePayload($user, ['kiosk_pin' => '9876']))
            ->assertRedirect(route('users.index'));

        $this->assertTrue(Hash::check('9876', $profile->fresh()->kiosk_pin));

        $this->actingAs($this->admin)
            ->put(route('users.update', $user), $this->updatePayload($user, ['kiosk_pin' => '']))
            ->assertRedirect(route('users.index'));

        $this->assertTrue(Hash::check('9876', $profile->fresh()->kiosk_pin));
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

    public function test_technician_store_creates_the_attendance_profile(): void
    {
        $this->actingAs($this->admin)
            ->post(route('technicians.store'), [
                'name' => 'Técnico Externo',
                'email' => 'externo@test.com',
                'phone' => '3311112233',
                'is_attendance_subject' => true,
                'can_remote_attendance' => true,
                'kiosk_pin' => '2468',
            ])
            ->assertRedirect(route('technicians.index'));

        $technician = Technician::first();
        $profile = PayrollProfile::where('user_id', $technician->user_id)->first();

        $this->assertNotNull($profile);
        $this->assertTrue($profile->is_attendance_subject);
        $this->assertTrue($profile->can_remote_attendance);
        $this->assertTrue(Hash::check('2468', $profile->kiosk_pin));
        $this->assertFalse($profile->is_payroll_subject);
    }

    public function test_technician_update_syncs_the_attendance_profile(): void
    {
        $user = User::factory()->create(['is_active' => true]);
        $technician = Technician::create([
            'user_id' => $user->id,
            'phone' => '3333333333',
            'status' => 'Activo',
        ]);

        $this->actingAs($this->admin)
            ->put(route('technicians.update', $technician), [
                'name' => $user->name,
                'email' => $user->email,
                'phone' => '3333333333',
                'status' => 'Activo',
                'is_attendance_subject' => true,
                'kiosk_pin' => '1111',
            ])
            ->assertRedirect(route('technicians.show', $technician->id));

        $profile = PayrollProfile::where('user_id', $user->id)->first();

        $this->assertNotNull($profile);
        $this->assertTrue($profile->is_attendance_subject);
        $this->assertTrue(Hash::check('1111', $profile->kiosk_pin));
    }
}
