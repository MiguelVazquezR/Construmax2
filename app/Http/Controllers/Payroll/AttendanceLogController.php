<?php

namespace App\Http\Controllers\Payroll;

use App\Http\Controllers\Controller;
use App\Http\Requests\Payroll\StoreAttendanceLogRequest;
use App\Http\Requests\Payroll\UpdateAttendanceLogRequest;
use App\Models\AttendanceLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Manual attendance corrections. Every change is audited with the acting
 * user, the timestamp and the reason.
 */
class AttendanceLogController extends Controller
{
    public function store(StoreAttendanceLogRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        AttendanceLog::create([
            'user_id' => $validated['user_id'],
            'type' => $validated['type'],
            'punched_at' => $validated['punched_at'],
            'source' => AttendanceLog::SOURCE_MANUAL,
            'identifier_method' => AttendanceLog::IDENTIFIER_MANUAL,
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'edited_by' => $request->user()->id,
            'edited_at' => now(),
            'edit_reason' => $validated['edit_reason'],
        ]);

        return back()->with('success', 'Marcaje registrado manualmente.');
    }

    public function update(UpdateAttendanceLogRequest $request, AttendanceLog $attendanceLog): RedirectResponse
    {
        $attendanceLog->update([
            'punched_at' => $request->validated('punched_at'),
            'type' => $request->validated('type') ?? $attendanceLog->type,
            'edited_by' => $request->user()->id,
            'edited_at' => now(),
            'edit_reason' => $request->validated('edit_reason'),
        ]);

        return back()->with('success', 'Marcaje actualizado y auditado.');
    }

    public function destroy(Request $request, AttendanceLog $attendanceLog): RedirectResponse
    {
        if (! $request->user()->can('payroll.periods.manage')) {
            abort(403);
        }

        $attendanceLog->delete();

        return back()->with('success', 'Marcaje eliminado.');
    }
}
