<?php

namespace App\Http\Controllers;

use App\Models\TechnicianSpecialty;
use Illuminate\Http\Request;

class TechnicianSpecialtyController extends Controller
{
    public function index()
    {
        return response()->json(
            TechnicianSpecialty::active()->orderBy('name')->get()
        );
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:technician_specialties,name',
        ]);

        $specialty = TechnicianSpecialty::create($validated);

        return response()->json([
            'message' => 'Especialidad creada correctamente.',
            'specialty' => $specialty,
        ]);
    }

    public function update(Request $request, TechnicianSpecialty $technicianSpecialty)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:technician_specialties,name,' . $technicianSpecialty->id,
            'is_active' => 'boolean',
        ]);

        $technicianSpecialty->update($validated);

        return response()->json([
            'message' => 'Especialidad actualizada correctamente.',
            'specialty' => $technicianSpecialty,
        ]);
    }

    public function destroy(TechnicianSpecialty $technicianSpecialty)
    {
        $technicianSpecialty->delete();

        return response()->json([
            'message' => 'Especialidad eliminada correctamente.',
        ]);
    }
}