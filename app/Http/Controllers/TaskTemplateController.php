<?php

namespace App\Http\Controllers;

use App\Models\TaskTemplate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TaskTemplateController extends Controller
{
    public function store(Request $request)
    {
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
        $taskTemplate->update(['is_active' => !$taskTemplate->is_active]);
        return back()->with('success', 'Estatus de la plantilla actualizado.');
    }

    public function destroy(TaskTemplate $taskTemplate)
    {
        $taskTemplate->delete();
        return back()->with('success', 'Plantilla de tareas eliminada.');
    }
}