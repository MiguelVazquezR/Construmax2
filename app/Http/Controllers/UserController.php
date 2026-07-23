<?php

namespace App\Http\Controllers;

use App\Models\User;
use Spatie\Permission\Models\Role; // Importar modelo Role
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Inertia\Inertia;

class UserController extends Controller
{
     public function index(Request $request)
    {
        $perPage = $request->input('perPage', 10);

        return Inertia::render('Users/Index', [
            'users' => User::with(['employee', 'roles'])
                ->where('id', '!=', 1) // Excluir al super admin
                ->whereDoesntHave('technician') // Solo usuarios sin técnico asociado
                ->filter($request->only('search'))
                ->orderBy('id', 'desc')
                ->paginate($perPage)
                ->withQueryString(),
            'filters' => $request->only(['search', 'perPage']),
        ]);
    }

    public function create()
    {
        // Enviamos los roles disponibles a la vista
        return Inertia::render('Users/Create', [
            'roles' => Role::all(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => ['required', Rules\Password::defaults()],
            'roles' => 'required|array|min:1', // Validar que se envíe al menos un rol
            'department' => 'required|string|max:255',
            'position' => 'required|string|max:255',
            'phone' => 'required|string|max:20',
        ]);

        DB::transaction(function () use ($validated) {
            $user = User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => Hash::make($validated['password']),
                'is_active' => true,
            ]);

            // Asignar roles (Spatie)
            $user->assignRole($validated['roles']);

            $user->employee()->create([
                'department' => $validated['department'],
                'position' => $validated['position'],
                'phone' => $validated['phone'],
            ]);
        });

        return redirect()->route('users.index')->with('success', 'Usuario creado y roles asignados correctamente.');
    }

    public function show(User $user)
    {
        $user->load([
            'employee',
            'roles',
            'ticketsAsSeller' => function ($query) {
                $query->orderBy('id', 'desc')
                      ->with('branch');
            },
        ]);

        return Inertia::render('Users/Show', [
            'user' => $user,
        ]);
    }

    public function edit(User $user)
    {
        return Inertia::render('Users/Edit', [
            'user' => $user->load(['employee', 'roles']),
            'roles' => Role::all(), // Enviamos roles para la edición
        ]);
    }

    public function update(Request $request, User $user)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users,email,' . $user->id,
            'password' => ['nullable', Rules\Password::defaults()],
            'roles' => 'required|array|min:1', // Roles requeridos en edición también
            'department' => 'required|string|max:255',
            'position' => 'required|string|max:255',
            'phone' => 'required|string|max:20',
        ]);

        DB::transaction(function () use ($validated, $user) {
            $userData = [
                'name' => $validated['name'],
                'email' => $validated['email'],
            ];

            if (!empty($validated['password'])) {
                $userData['password'] = Hash::make($validated['password']);
            }

            $user->update($userData);

            // Sincronizar roles (reemplaza los anteriores por los nuevos)
            $user->syncRoles($validated['roles']);

            $user->employee()->updateOrCreate(
                ['user_id' => $user->id],
                [
                    'department' => $validated['department'],
                    'position' => $validated['position'],
                    'phone' => $validated['phone'],
                ]
            );
        });

        return redirect()->route('users.index')->with('success', 'Usuario actualizado exitosamente.');
    }

    /**
     * Clean up all foreign key references to the given user IDs before deletion.
     *
     * Strategy per table:
     *   - Nullable FK          → set user_id = null (e.g. ticket_tasks)
     *   - NOT NULL, owns data  → DELETE the row (e.g. calendars, calendar_participants, tech_payments)
     *   - NOT NULL, important  → reassign to admin (id=1) (e.g. budgets)
     */
    private function nullifyUserReferences(array $ids): void
    {
        // Nullable FK — safe to just unlink
        DB::table('ticket_tasks')->whereIn('user_id', $ids)->update(['user_id' => null]);

        // NOT NULL — delete the row (acceptable data loss for mistakenly-created users)
        DB::table('calendars')->whereIn('user_id', $ids)->delete();
        DB::table('calendar_participants')->whereIn('user_id', $ids)->delete();
        DB::table('technician_payments')->whereIn('user_id', $ids)->delete();
        DB::table('field_work_schedules')->whereIn('user_id', $ids)->delete();

        // NOT NULL but important data — reassign to admin (id=1)
        DB::table('budgets')->whereIn('user_id', $ids)->update(['user_id' => 1]);
    }

    public function destroy(User $user)
    {
        if ($user->id === 1) {
            return back()->with('error', 'No se puede eliminar al super administrador.');
        }

        $this->nullifyUserReferences([$user->id]);

        $user->delete();
        return back()->with('success', 'Usuario eliminado correctamente.');
    }

    public function bulkDestroy(Request $request)
    {
        $validated = $request->validate([
            'ids'   => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'exists:users,id'],
        ]);

        // Exclude super admin (id=1) from bulk deletion
        $ids = array_values(array_filter($validated['ids'], fn ($id) => $id !== 1));

        if (empty($ids)) {
            return back()->with('error', 'No se puede eliminar al super administrador.');
        }

        $this->nullifyUserReferences($ids);

        User::whereIn('id', $ids)->delete();

        return back()->with('success', count($ids) . ' usuarios eliminados correctamente.');
    }

    public function toggleStatus(User $user)
    {
        $user->is_active = !$user->is_active;
        $user->save();
        $message = $user->is_active ? 'Usuario activado.' : 'Usuario dado de baja.';
        return back()->with('success', $message);
    }
}
