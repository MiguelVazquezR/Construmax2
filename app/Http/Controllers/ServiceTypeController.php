<?php

namespace App\Http\Controllers;

use App\Models\ServiceType;
use Illuminate\Http\Request;

class ServiceTypeController extends Controller
{
    public function index()
    {
        return response()->json(
            ServiceType::active()->orderBy('name')->get()
        );
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:service_types,name',
        ]);

        $serviceType = ServiceType::create($validated);

        return response()->json([
            'message' => 'Tipo de servicio creado correctamente.',
            'serviceType' => $serviceType,
        ]);
    }

    public function update(Request $request, ServiceType $serviceType)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:service_types,name,' . $serviceType->id,
            'is_active' => 'boolean',
        ]);

        $serviceType->update($validated);

        return response()->json([
            'message' => 'Tipo de servicio actualizado correctamente.',
            'serviceType' => $serviceType,
        ]);
    }

    public function destroy(ServiceType $serviceType)
    {
        $serviceType->delete();

        return response()->json([
            'message' => 'Tipo de servicio eliminado correctamente.',
        ]);
    }
}
