<?php

namespace App\Http\Controllers;

use App\Models\DepositType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DepositTypeController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(
            DepositType::orderBy('name')->get()
        );
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:deposit_types,name',
        ]);

        $depositType = DepositType::create($validated);

        return response()->json([
            'message'     => 'Tipo de depósito creado correctamente.',
            'depositType' => $depositType,
        ]);
    }

    public function update(Request $request, DepositType $depositType): JsonResponse
    {
        $validated = $request->validate([
            'name'      => 'required|string|max:255|unique:deposit_types,name,' . $depositType->id,
            'is_active' => 'boolean',
        ]);

        $depositType->update($validated);

        return response()->json([
            'message'     => 'Tipo de depósito actualizado correctamente.',
            'depositType' => $depositType,
        ]);
    }

    public function destroy(DepositType $depositType): JsonResponse
    {
        $depositType->delete();

        return response()->json([
            'message' => 'Tipo de depósito eliminado correctamente.'
        ]);
    }
}
