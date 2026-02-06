<?php

namespace App\Http\Controllers;

use App\Models\User;
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
            'users' => User::with('employee')
                ->filter($request->only('search'))
                ->orderBy('id', 'desc')
                ->paginate($perPage)
                ->withQueryString(),
            'filters' => $request->only(['search', 'perPage']),
        ]);
    }

    public function create()
    {
        return Inertia::render('Users/Create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => ['required', Rules\Password::defaults()],
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

            $user->employee()->create([
                'department' => $validated['department'],
                'position' => $validated['position'],
                'phone' => $validated['phone'],
            ]);
        });

        return redirect()->route('users.index')->with('success', 'Usuario creado exitosamente.');
    }

    // --- NUEVOS MÉTODOS ---

    public function show(User $user)
    {
        return Inertia::render('Users/Show', [
            'user' => $user->load('employee'),
        ]);
    }

    public function edit(User $user)
    {
        return Inertia::render('Users/Edit', [
            'user' => $user->load('employee'),
        ]);
    }

    public function update(Request $request, User $user)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            // Ignoramos el email del usuario actual para que no marque error de "ya existe"
            'email' => 'required|string|email|max:255|unique:users,email,' . $user->id,
            // Password es nullable en edición
            'password' => ['nullable', Rules\Password::defaults()],
            'department' => 'required|string|max:255',
            'position' => 'required|string|max:255',
            'phone' => 'required|string|max:20',
        ]);

        DB::transaction(function () use ($validated, $user) {
            $userData = [
                'name' => $validated['name'],
                'email' => $validated['email'],
            ];

            // Solo actualizamos password si se envió algo
            if (!empty($validated['password'])) {
                $userData['password'] = Hash::make($validated['password']);
            }

            $user->update($userData);

            // updateOrCreate por si el usuario antiguo no tenía registro en employees
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

    public function destroy(User $user)
    {
        $user->delete();
        return back()->with('success', 'Usuario eliminado correctamente.');
    }

    public function toggleStatus(User $user)
    {
        $user->is_active = !$user->is_active;
        $user->save();
        $message = $user->is_active ? 'Usuario activado.' : 'Usuario dado de baja.';
        return back()->with('success', $message);
    }
}