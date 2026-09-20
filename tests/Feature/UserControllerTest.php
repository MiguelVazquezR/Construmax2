<?php

namespace Tests\Feature;

use App\Models\PayrollProfile;
use App\Models\User;
use App\Models\VacationAdjustment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class UserControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $superAdmin;

    protected function setUp(): void
    {
        parent::setUp();
        // Create super admin first so it gets id=1
        $this->superAdmin = User::factory()->create(['is_active' => true]);

        Permission::create(['name' => 'users.toggle-status', 'guard_name' => 'web', 'category' => 'Usuarios', 'description' => 'Activar o desactivar acceso a usuarios']);

        $this->admin = User::factory()->create(['is_active' => true]);
        $this->admin->givePermissionTo('users.toggle-status');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    // --- index ---

    public function test_index_renders_users_page(): void
    {
        User::factory()->count(3)->create(['is_active' => true]);

        $this->actingAs($this->admin)
            ->get(route('users.index'))
            ->assertInertia(fn ($page) => $page
                ->component('Users/Index')
                ->has('users.data')
            );
    }

    public function test_index_excludes_super_admin(): void
    {
        // superAdmin (id=1 from setUp) should be excluded from listing
        User::factory()->create(['name' => 'Normal User']);

        $this->actingAs($this->admin)
            ->get(route('users.index'))
            ->assertInertia(fn ($page) => $page
                ->has('users.data')
            );
    }

    // --- create ---

    public function test_create_renders_form(): void
    {
        $this->actingAs($this->admin)
            ->get(route('users.create'))
            ->assertInertia(fn ($page) => $page
                ->component('Users/Create')
                ->has('roles')
            );
    }

    // --- store ---

    public function test_store_creates_user(): void
    {
        $role = Role::create(['name' => 'Operator', 'guard_name' => 'web']);

        $data = [
            'name' => 'New User',
            'email' => 'newuser@test.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'roles' => ['Operator'],
            'department' => 'IT',
            'position' => 'Developer',
            'phone' => '8112345678',
        ];

        $this->actingAs($this->admin)
            ->post(route('users.store'), $data)
            ->assertRedirect(route('users.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('users', ['email' => 'newuser@test.com']);
        $this->assertDatabaseHas('employees', ['department' => 'IT']);
    }

    public function test_store_fails_validation_without_required_fields(): void
    {
        $this->actingAs($this->admin)
            ->post(route('users.store'), [])
            ->assertSessionHasErrors([
                'name', 'email', 'password', 'roles',
                'department', 'position', 'phone',
            ]);
    }

    // --- show ---

    public function test_show_displays_user(): void
    {
        $user = User::factory()->create();

        $this->actingAs($this->admin)
            ->get(route('users.show', $user))
            ->assertInertia(fn ($page) => $page
                ->component('Users/Show')
                ->has('user')
            );
    }

    public function test_show_exposes_the_vacation_balance_of_the_collaborator(): void
    {
        Carbon::setTestNow('2026-02-01');

        $user = User::factory()->create();

        PayrollProfile::create([
            'user_id' => $user->id,
            'hire_date' => '2026-01-01',
            'is_payroll_subject' => true,
        ]);

        VacationAdjustment::create([
            'user_id' => $user->id,
            'type' => VacationAdjustment::TYPE_INITIAL,
            'days' => 10,
            'reason' => 'Saldo previo al sistema',
        ]);

        $this->actingAs($this->admin)
            ->get(route('users.show', $user))
            ->assertInertia(fn ($page) => $page
                ->component('Users/Show')
                ->has('vacation.balance')
                ->has('vacation.adjustments', 1)
                ->has('vacation.requests')
                ->where('vacation.adjustments.0.type_label', 'Saldo inicial')
                ->where('vacation.balance.adjustment_days', 10)
                ->where('vacation.balance.available_days', 10.92)
            );
    }

    // --- edit ---

    public function test_edit_renders_form(): void
    {
        $user = User::factory()->create();

        $this->actingAs($this->admin)
            ->get(route('users.edit', $user))
            ->assertInertia(fn ($page) => $page
                ->component('Users/Edit')
                ->has('user')
                ->has('roles')
            );
    }

    // --- update ---

    public function test_update_modifies_user(): void
    {
        $role = Role::create(['name' => 'Editor', 'guard_name' => 'web']);

        $user = User::factory()->create(['name' => 'Old Name']);
        $user->employee()->create([
            'department' => 'Old Dept',
            'position' => 'Old Pos',
            'phone' => '0000000000',
        ]);

        $data = [
            'name' => 'Updated Name',
            'email' => $user->email,
            'roles' => ['Editor'],
            'department' => 'New Dept',
            'position' => 'New Pos',
            'phone' => '8112345678',
        ];

        $this->actingAs($this->admin)
            ->put(route('users.update', $user), $data)
            ->assertRedirect(route('users.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'Updated Name',
        ]);
        $this->assertDatabaseHas('employees', [
            'user_id' => $user->id,
            'department' => 'New Dept',
        ]);
    }

    // --- toggleStatus ---

    public function test_toggle_status_deactivates_and_reactivates_user(): void
    {
        $user = User::factory()->create(['is_active' => true]);

        $this->actingAs($this->admin)
            ->put(route('users.toggle-status', $user), ['termination_date' => '2026-09-20'])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'is_active' => false,
        ]);

        $this->actingAs($this->admin)
            ->put(route('users.toggle-status', $user))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'is_active' => true,
        ]);
    }

    public function test_dismissing_a_user_stores_the_termination_date(): void
    {
        $user = User::factory()->create(['is_active' => true]);

        PayrollProfile::create([
            'user_id' => $user->id,
            'hire_date' => '2024-01-01',
            'is_payroll_subject' => true,
        ]);

        $this->actingAs($this->admin)
            ->put(route('users.toggle-status', $user), ['termination_date' => '2026-09-15'])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseHas('payroll_profiles', [
            'user_id' => $user->id,
            'termination_date' => '2026-09-15 00:00:00',
        ]);
    }

    public function test_dismissing_a_user_defaults_the_termination_date_to_today(): void
    {
        $user = User::factory()->create(['is_active' => true]);

        PayrollProfile::create([
            'user_id' => $user->id,
            'hire_date' => '2024-01-01',
            'is_payroll_subject' => true,
        ]);

        $this->actingAs($this->admin)
            ->put(route('users.toggle-status', $user))
            ->assertRedirect()
            ->assertSessionHas('success');

        $profile = PayrollProfile::where('user_id', $user->id)->first();

        $this->assertNotNull($profile->termination_date);
        $this->assertSame(now()->toDateString(), $profile->termination_date->toDateString());
    }

    public function test_reactivating_a_user_clears_the_termination_date(): void
    {
        $user = User::factory()->create(['is_active' => false]);

        PayrollProfile::create([
            'user_id' => $user->id,
            'hire_date' => '2024-01-01',
            'termination_date' => '2026-09-15',
            'is_payroll_subject' => true,
        ]);

        $this->actingAs($this->admin)
            ->put(route('users.toggle-status', $user))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertNull(PayrollProfile::where('user_id', $user->id)->first()->termination_date);
    }

    public function test_termination_date_cannot_be_before_the_hire_date(): void
    {
        $user = User::factory()->create(['is_active' => true]);

        PayrollProfile::create([
            'user_id' => $user->id,
            'hire_date' => '2024-01-01',
            'is_payroll_subject' => true,
        ]);

        $this->actingAs($this->admin)
            ->put(route('users.toggle-status', $user), ['termination_date' => '2023-12-31'])
            ->assertSessionHasErrors('termination_date');

        // The user stays active.
        $this->assertDatabaseHas('users', ['id' => $user->id, 'is_active' => true]);
    }

    public function test_toggle_status_requires_the_permission(): void
    {
        $user = User::factory()->create(['is_active' => true]);
        $plain = User::factory()->create(['is_active' => true]);

        $this->actingAs($plain)
            ->put(route('users.toggle-status', $user), ['termination_date' => '2026-09-15'])
            ->assertForbidden();

        $this->assertDatabaseHas('users', ['id' => $user->id, 'is_active' => true]);
    }

    public function test_the_super_admin_cannot_be_dismissed(): void
    {
        $this->actingAs($this->admin)
            ->put(route('users.toggle-status', $this->superAdmin), ['termination_date' => '2026-09-15'])
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertDatabaseHas('users', ['id' => $this->superAdmin->id, 'is_active' => true]);
    }

    // --- destroy ---

    public function test_destroy_deletes_user(): void
    {
        $user = User::factory()->create();

        $this->actingAs($this->admin)
            ->delete(route('users.destroy', $user))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('users', ['id' => $user->id]);
    }
}
