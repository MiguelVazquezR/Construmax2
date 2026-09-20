<?php

namespace App\Http\Controllers\Payroll;

use App\Http\Controllers\Controller;
use App\Http\Requests\Payroll\StoreIncidentRequest;
use App\Models\Incident;
use App\Services\Payroll\ImageAttachmentService;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Incidents are registered, listed and deleted from the payroll period detail
 * (the pre-payroll drawer), so this controller only exposes the write actions.
 */
class IncidentController extends Controller
{
    public function __construct(
        private readonly ImageAttachmentService $attachmentService,
    ) {}

    public function store(StoreIncidentRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $start = CarbonImmutable::parse($validated['start_date']);
        $end = isset($validated['end_date']) ? CarbonImmutable::parse($validated['end_date']) : $start;

        $incident = Incident::create([
            'user_id' => $validated['user_id'],
            'type' => $validated['type'],
            'start_date' => $start->toDateString(),
            'end_date' => $end->toDateString(),
            'days' => abs($start->diffInDays($end)) + 1,
            'is_paid' => $validated['is_paid'] ?? null,
            'status' => Incident::STATUS_APPROVED,
            'notes' => $validated['notes'] ?? null,
            'created_by' => $request->user()->id,
            'approved_by' => $request->user()->id,
            'approved_at' => now(),
        ]);

        if ($request->hasFile('support')) {
            $this->attachmentService->attach($incident, $request->file('support'), 'support');
        }

        return back()->with('success', 'Incidencia registrada.');
    }

    public function destroy(Request $request, Incident $incident): RedirectResponse
    {
        if (! $request->user()->can('payroll.incidents.manage')) {
            abort(403);
        }

        $incident->delete();

        return back()->with('success', 'Incidencia eliminada.');
    }
}
