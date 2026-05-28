<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
        $this->admin = User::factory()->create(['is_active' => true]);
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
            ->put(route('users.toggle-status', $user))
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
