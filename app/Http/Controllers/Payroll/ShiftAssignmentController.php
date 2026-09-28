<?php

namespace App\Http\Controllers\Payroll;

use App\Http\Controllers\Controller;
use App\Http\Requests\Payroll\StoreShiftAssignmentRequest;
use App\Models\ShiftAssignment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Legacy endpoints of the shift assignments panel, retired from the shifts
 * screen (assignments are now managed from the user and technician forms
 * through AssignUserShiftAction). They stay available for a future re-enable
 * of the panel.
 */
class ShiftAssignmentController extends Controller
{
    public function store(StoreShiftAssignmentRequest $request): RedirectResponse
    {
        ShiftAssignment::create($request->validated());

        return back()->with('success', 'Asignación de horario registrada.');
    }

    public function destroy(Request $request, ShiftAssignment $assignment): RedirectResponse
    {
        if (! $request->user()->can('payroll.shifts.manage')) {
            abort(403);
        }

        $assignment->delete();

        return back()->with('success', 'Asignación eliminada correctamente.');
    }
}
