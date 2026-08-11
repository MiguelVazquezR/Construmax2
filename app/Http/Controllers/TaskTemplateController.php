<?php

namespace App\Http\Controllers;

use App\Models\TaskTemplate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class TaskTemplateController extends Controller
{
    use AuthorizesRequests;

    public function index(Request $request)
    {
        $query = TaskTemplate::with('items')->orderBy('name');

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->whereRaw('name COLLATE utf8mb4_unicode_ci LIKE ?', ["%{$search}%"]);
        }

        return response()->json($query->get());
    }

    public function store(Request $request)
    {
        $this->authorize('tickets.create-tasks-template');
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.name' => 'required|string|max:255',
            'items.*.description' => 'nullable|string',
        ]);

        DB::transaction(function () use ($validated) {
            $template = TaskTemplate::create([
                'name' => $validated['name'],
                'description' => $validated['description'] ?? null,
                'is_active' => true,
            ]);

            $template->items()->createMany($validated['items']);
        });

        return back()->with('success', 'Plantilla de tareas creada exitosamente.');
    }

    public function update(Request $request, TaskTemplate $taskTemplate)
    {
        $this->authorize('tickets.edit-tasks-template');
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.name' => 'required|string|max:255',
            'items.*.description' => 'nullable|string',
        ]);

        DB::transaction(function () use ($validated, $taskTemplate) {
            $taskTemplate->update([
                'name' => $validated['name'],
                'description' => $validated['description'] ?? null,
            ]);

            $taskTemplate->items()->delete();
            $taskTemplate->items()->createMany($validated['items']);
        });

        return back()->with('success', 'Plantilla de tareas actualizada exitosamente.');
    }

    public function toggleStatus(TaskTemplate $taskTemplate)
    {
        $this->authorize('tickets.edit-tasks-template');

        $taskTemplate->update(['is_active' => !$taskTemplate->is_active]);
        return back()->with('success', 'Estatus de la plantilla actualizado.');
    }

    public function destroy(TaskTemplate $taskTemplate)
    {
        $this->authorize('tickets.delete-tasks-template');

        $taskTemplate->delete();
        return back()->with('success', 'Plantilla de tareas eliminada.');
    }
}