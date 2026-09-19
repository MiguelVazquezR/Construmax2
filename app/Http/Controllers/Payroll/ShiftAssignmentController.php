<?php

namespace App\Http\Controllers\Payroll;

use App\Http\Controllers\Controller;
use App\Http\Requests\Payroll\StoreShiftAssignmentRequest;
use App\Models\ShiftAssignment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ShiftAssignmentController extends Controller
{
    public function store(StoreShiftAssignmentRequest $request): RedirectResponse
    {
        ShiftAssignment::create($request->validated());

        return back()->with('success', 'Asignación de turno registrada.');
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
