<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RolePermissionControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $superAdmin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->superAdmin = User::factory()->create(['id' => 1, 'is_active' => true]);
        $this->admin = User::factory()->create(['is_active' => true]);
    }

    // --- index ---

    public function test_index_renders_roles_permissions_page(): void
    {
        $this->actingAs($this->admin)
            ->get(route('config.roles-permissions.index'))
            ->assertInertia(fn ($page) => $page
                ->component('RolePermissions/Index')
                ->has('roles')
                ->has('allPermissions')
                ->has('permissionsGrouped')
            );
    }

    // --- storeRole ---

    public function test_store_role_creates_new_role(): void
    {
        Permission::create(['name' => 'test.permission', 'guard_name' => 'web', 'category' => 'Test', 'description' => 'Test permission']);

        $this->actingAs($this->admin)
            ->post(route('config.roles.store'), [
                'name' => 'Test Role',
                'permissions' => [],
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseHas('roles', ['name' => 'Test Role']);
    }

    public function test_store_role_requires_unique_name(): void
    {
        Role::create(['name' => 'Duplicate', 'guard_name' => 'web']);

        $this->actingAs($this->admin)
            ->post(route('config.roles.store'), [
                'name' => 'Duplicate',
            ])
            ->assertSessionHasErrors('name');
    }

    // --- updateRole ---

    public function test_update_role_modifies_role(): void
    {
        $role = Role::create(['name' => 'Old Role', 'guard_name' => 'web']);

        $this->actingAs($this->admin)
            ->put(route('config.roles.update', $role), [
                'name' => 'New Role',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseHas('roles', ['id' => $role->id, 'name' => 'New Role']);
    }

    // --- destroyRole ---

    public function test_destroy_role_deletes_role_without_users(): void
    {
        $role = Role::create(['name' => 'Empty Role', 'guard_name' => 'web']);

        $this->actingAs($this->admin)
            ->delete(route('config.roles.destroy', $role))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('roles', ['id' => $role->id]);
    }

    public function test_destroy_role_fails_when_role_has_users(): void
    {
        $role = Role::create(['name' => 'Occupied Role', 'guard_name' => 'web']);
        $this->admin->assignRole($role);

        $this->actingAs($this->admin)
            ->delete(route('config.roles.destroy', $role))
            ->assertRedirect()
            ->assertSessionHas('error');
    }

    // --- storePermission (super admin only) ---

    public function test_store_permission_requires_super_admin(): void
    {
        $this->actingAs($this->admin)
            ->post(route('config.permissions.store'), [
                'name' => 'new.permission',
                'category' => 'Test',
                'description' => 'A test permission',
            ])
            ->assertForbidden();
    }

    public function test_store_permission_succeeds_for_super_admin(): void
    {
        $this->actingAs($this->superAdmin)
            ->post(route('config.permissions.store'), [
                'name' => 'new.permission',
                'category' => 'Test',
                'description' => 'A test permission',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseHas('permissions', ['name' => 'new.permission']);
    }

    // --- updatePermission (super admin only) ---

    public function test_update_permission_requires_super_admin(): void
    {
        $permission = Permission::create(['name' => 'edit.me', 'guard_name' => 'web', 'category' => 'Edit', 'description' => 'Edit perm']);

        $this->actingAs($this->admin)
            ->put(route('config.permissions.update', $permission), [
                'name' => 'edit.me',
                'category' => 'Updated',
                'description' => 'Updated desc',
            ])
            ->assertForbidden();
    }

    public function test_update_permission_succeeds_for_super_admin(): void
    {
        $permission = Permission::create([
            'name' => 'update.me',
            'guard_name' => 'web',
            'category' => 'Old',
            'description' => 'Old desc',
        ]);

        $this->actingAs($this->superAdmin)
            ->put(route('config.permissions.update', $permission), [
                'name' => 'update.me',
                'category' => 'New Cat',
                'description' => 'New description',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseHas('permissions', [
            'id' => $permission->id,
            'category' => 'New Cat',
            'description' => 'New description',
        ]);
    }

    // --- destroyPermission (super admin only) ---

    public function test_destroy_permission_requires_super_admin(): void
    {
        $permission = Permission::create(['name' => 'delete.me', 'guard_name' => 'web', 'category' => 'Delete', 'description' => 'Delete perm']);

        $this->actingAs($this->admin)
            ->delete(route('config.permissions.destroy', $permission))
            ->assertForbidden();
    }

    public function test_destroy_permission_succeeds_for_super_admin(): void
    {
        $permission = Permission::create(['name' => 'del.perm', 'guard_name' => 'web', 'category' => 'Misc', 'description' => 'Delete perm']);

        $this->actingAs($this->superAdmin)
            ->delete(route('config.permissions.destroy', $permission))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('permissions', ['id' => $permission->id]);
    }
}
