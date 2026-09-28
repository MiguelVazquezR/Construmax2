<?php

namespace App\Http\Controllers\Payroll;

use App\Http\Controllers\Controller;
use App\Http\Requests\Payroll\StoreShiftRequest;
use App\Http\Requests\Payroll\UpdateShiftRequest;
use App\Models\PayrollSetting;
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

        // Assignments feed the "who has this schedule" dialog of the shift
        // rows, including a technician flag for collaborators that have a
        // technician record. The legacy "Asignaciones" panel is still
        // retired, so the props stay available for a future re-enable (the
        // shift-assignments endpoints remain active as well).
        $assignments = ShiftAssignment::with(['user:id,name', 'user.technician:id,user_id', 'shift:id,name'])
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
            'shiftTypeDescriptions' => Shift::TYPE_DESCRIPTIONS,
            'weekDays' => Shift::DAYS,
            'assignmentTypes' => ShiftAssignment::TYPES,
            // Shifts without their own tolerance fall back to the global
            // setting, so the table can show which value applies.
            'globalLateToleranceMinutes' => PayrollSetting::current()->late_tolerance_minutes,
        ]);
    }

    public function store(StoreShiftRequest $request): RedirectResponse
    {
        Shift::create($request->validated());

        return back()->with('success', 'Horario creado correctamente.');
    }

    public function update(UpdateShiftRequest $request, Shift $shift): RedirectResponse
    {
        $shift->update($request->validated());

        return back()->with('success', 'Horario actualizado correctamente.');
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
            return back()->with('error', 'El horario está asignado a colaboradores: cámbialo o quítalo desde la ficha del colaborador (usuario o técnico).');
        }

        $shift->delete();

        return back()->with('success', 'Horario eliminado correctamente.');
    }
}
