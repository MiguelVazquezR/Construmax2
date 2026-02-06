<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;

class UserController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $perPage = $request->input('perPage', 10);
        
        return Inertia::render('Users/Index', [
            'users' => User::with('employee') // Eager loading para optimizar
                ->filter($request->only('search'))
                ->orderBy('id', 'desc')
                ->paginate($perPage)
                ->withQueryString(), // Mantiene los filtros en la URL
            'filters' => $request->only(['search', 'perPage']),
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, User $user)
    {
        // Lógica de validación y actualización
        // ... (Implementaremos el formulario modal más adelante)
        return back()->with('success', 'Usuario actualizado correctamente.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(User $user)
    {
        $user->delete();
        return back()->with('success', 'Usuario eliminado correctamente.');
    }

    /**
     * Toggle status (Dar de baja / Activar)
     */
    public function toggleStatus(User $user)
    {
        $user->is_active = !$user->is_active;
        $user->save();

        $message = $user->is_active ? 'Usuario activado.' : 'Usuario dado de baja.';
        return back()->with('success', $message);
    }
}