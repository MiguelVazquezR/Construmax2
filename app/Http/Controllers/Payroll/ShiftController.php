<?php

namespace App\Http\Controllers\Payroll;

use App\Http\Controllers\Controller;
use App\Http\Requests\Payroll\StoreShiftRequest;
use App\Http\Requests\Payroll\UpdateShiftRequest;
use App\Models\Shift;
use App\Models\ShiftAssignment;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ShiftController extends Controller
{
    public function index(Request $request): Response
    {
        if (! $request->user()->can('payroll.shifts.manage')) {
            abort(403);
        }

        $shifts = Shift::query()
            ->withCount('assignments')
            ->orderBy('name')
            ->get();

        $assignments = ShiftAssignment::with(['user:id,name', 'shift:id,name'])
            ->orderByDesc('start_date')
            ->orderByDesc('id')
            ->get();

        $users = User::query()
            ->whereHas('payrollProfile', fn ($query) => $query->where('is_attendance_subject', true))
            ->orderBy('name')
            ->get(['id', 'name']);

        return Inertia::render('Payroll/Shifts/Index', [
            'shifts' => $shifts,
            'assignments' => $assignments,
            'users' => $users,
            'shiftTypes' => Shift::TYPES,
            'weekDays' => Shift::DAYS,
            'assignmentTypes' => ShiftAssignment::TYPES,
        ]);
    }

    public function store(StoreShiftRequest $request): RedirectResponse
    {
        Shift::create($request->validated());

        return back()->with('success', 'Turno creado correctamente.');
    }

    public function update(UpdateShiftRequest $request, Shift $shift): RedirectResponse
    {
        $shift->update($request->validated());

        return back()->with('success', 'Turno actualizado correctamente.');
    }

    public function destroy(Request $request, Shift $shift): RedirectResponse
    {
        if (! $request->user()->can('payroll.shifts.manage')) {
            abort(403);
        }

        $usedInAssignments = $shift->assignments()->exists()
            || ShiftAssignment::query()
                ->whereJsonContains('rotation', $shift->id)
                ->exists();

        if ($usedInAssignments) {
            return back()->with('error', 'El turno está asignado a colaboradores: elimina o cambia sus asignaciones primero.');
        }

        $shift->delete();

        return back()->with('success', 'Turno eliminado correctamente.');
    }
}
