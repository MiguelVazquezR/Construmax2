<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class RolePermissionController extends Controller
{
    /**
     * Muestra la vista principal con Roles y Permisos.
     */
    public function index()
    {
        // Obtenemos roles con sus permisos asignados
        $roles = Role::with('permissions')->orderBy('id')->get();

        // Obtenemos todos los permisos
        $permissions = Permission::orderBy('category')->orderBy('name')->get();

        // Agrupamos permisos por categoría para la vista (facilita la UI)
        $permissionsGrouped = $permissions->groupBy('category');

        return Inertia::render('RolePermissions/Index', [
            'roles' => $roles,
            'allPermissions' => $permissions, // Lista plana para tabla
            'permissionsGrouped' => $permissionsGrouped, // Lista agrupada para asignar
        ]);
    }

    // ==========================================
    // LÓGICA DE ROLES
    // ==========================================

    public function storeRole(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:roles,name'],
            'permissions' => ['array'] // Array de IDs de permisos
        ]);

        $role = Role::create(['name' => $validated['name'], 'guard_name' => 'web']);

        if (!empty($validated['permissions'])) {
            $role->syncPermissions($validated['permissions']);
        }

        return back()->with('success', 'Rol creado correctamente.');
    }

    public function updateRole(Request $request, Role $role)
    {
        // Evitar editar roles críticos si es necesario (ej. Super Admin)
        // if ($role->name === 'Super Admin') return back()->with('error', 'No puedes editar este rol.');

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('roles', 'name')->ignore($role->id)],
            'permissions' => ['array']
        ]);

        $role->update(['name' => $validated['name']]);

        if (isset($validated['permissions'])) {
            $role->syncPermissions($validated['permissions']);
        }

        return back()->with('success', 'Rol actualizado correctamente.');
    }

    public function destroyRole(Role $role)
    {
        if ($role->users()->count() > 0) {
            return back()->with('error', 'No se puede eliminar el rol porque tiene usuarios asignados.');
        }

        $role->delete();
        return back()->with('success', 'Rol eliminado correctamente.');
    }

    // ==========================================
    // LÓGICA DE PERMISOS (Solo ID 1)
    // ==========================================

    private function checkDeveloperAccess()
    {
        if (Auth::id() !== 1) {
            abort(403, 'Solo el desarrollador puede gestionar permisos base.');
        }
    }

    public function storePermission(Request $request)
    {
        $this->checkDeveloperAccess();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:permissions,name'],
            'category' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string', 'max:255'],
        ]);

        Permission::create([
            'name' => $validated['name'],
            'category' => $validated['category'],
            'description' => $validated['description'],
            'guard_name' => 'web'
        ]);

        return back()->with('success', 'Permiso creado (Sistema).');
    }

    public function updatePermission(Request $request, Permission $permission)
    {
        $this->checkDeveloperAccess();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('permissions', 'name')->ignore($permission->id)],
            'category' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string', 'max:255'],
        ]);

        $permission->update($validated);

        return back()->with('success', 'Permiso actualizado (Sistema).');
    }

    public function destroyPermission(Permission $permission)
    {
        $this->checkDeveloperAccess();
        
        $permission->delete();
        return back()->with('success', 'Permiso eliminado (Sistema).');
    }
}