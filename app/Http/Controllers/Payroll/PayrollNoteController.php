<?php

namespace App\Http\Controllers\Payroll;

use App\Http\Controllers\Controller;
use App\Http\Requests\Payroll\StorePayrollNoteRequest;
use App\Http\Requests\Payroll\UpdatePayrollNoteRequest;
use App\Models\PayrollNote;
use App\Models\PayrollPeriod;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Free comments of the payroll team about a collaborator inside a period.
 * They are shown at the bottom of every collaborator panel and travel with
 * the pre-payroll sheet.
 */
class PayrollNoteController extends Controller
{
    public function store(StorePayrollNoteRequest $request, PayrollPeriod $period): RedirectResponse
    {
        PayrollNote::create([
            'payroll_period_id' => $period->id,
            'user_id' => $request->validated('user_id'),
            'body' => $request->validated('body'),
            'created_by' => $request->user()->id,
        ]);

        return back()->with('success', 'Comentario agregado.');
    }

    public function update(UpdatePayrollNoteRequest $request, PayrollNote $note): RedirectResponse
    {
        $note->update(['body' => $request->validated('body')]);

        return back()->with('success', 'Comentario actualizado.');
    }

    public function destroy(Request $request, PayrollNote $note): RedirectResponse
    {
        if (! $request->user()->can('payroll.periods.manage')) {
            abort(403);
        }

        $note->delete();

        return back()->with('success', 'Comentario eliminado.');
    }
}
