<?php

namespace App\Http\Controllers;

use App\Http\Requests\Expenses\StoreExpenseCategoryRequest;
use App\Http\Requests\Expenses\UpdateExpenseCategoryRequest;
use App\Models\ExpenseCategory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ExpenseCategoryController extends Controller
{
    /**
     * Categories catalog used by the expenses module (data for the manager modal).
     */
    public function index(Request $request): JsonResponse
    {
        if (!$request->user()->can('expenses.categories.manage')) {
            abort(403);
        }

        return response()->json(
            ExpenseCategory::withCount('expenses')->orderBy('name')->get()
        );
    }

    public function store(StoreExpenseCategoryRequest $request): JsonResponse
    {
        $category = ExpenseCategory::create([
            'name' => $request->validated('name'),
            'is_active' => true,
        ]);

        return response()->json([
            'message' => 'Categoría creada correctamente.',
            'category' => $category,
        ]);
    }

    public function update(UpdateExpenseCategoryRequest $request, ExpenseCategory $category): JsonResponse
    {
        $category->update([
            'name' => $request->validated('name'),
            'is_active' => $request->boolean('is_active', $category->is_active),
        ]);

        return response()->json([
            'message' => 'Categoría actualizada correctamente.',
            'category' => $category,
        ]);
    }

    public function destroy(Request $request, ExpenseCategory $category): JsonResponse
    {
        if (!$request->user()->can('expenses.categories.manage')) {
            abort(403);
        }

        $category->delete();

        return response()->json(['message' => 'Categoría eliminada correctamente.']);
    }
}
