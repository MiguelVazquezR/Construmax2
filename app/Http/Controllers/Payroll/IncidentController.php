<?php

namespace App\Http\Controllers\Payroll;

use App\Http\Controllers\Controller;
use App\Http\Requests\Payroll\StoreIncidentRequest;
use App\Models\Incident;
use App\Models\User;
use App\Services\Payroll\ImageAttachmentService;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class IncidentController extends Controller
{
    public function __construct(
        private readonly ImageAttachmentService $attachmentService,
    ) {}

    public function index(Request $request): Response
    {
        if (! $request->user()->can('payroll.incidents.manage')) {
            abort(403);
        }

        $incidents = Incident::query()
            ->with(['user:id,name', 'creator:id,name', 'approver:id,name'])
            ->when($request->filled('user_id'), fn ($query) => $query->where('user_id', $request->integer('user_id')))
            ->when($request->filled('type'), fn ($query) => $query->where('type', $request->string('type')))
            ->when($request->filled('from'), fn ($query) => $query->whereDate('start_date', '>=', $request->string('from')))
            ->when($request->filled('to'), fn ($query) => $query->whereDate('start_date', '<=', $request->string('to')))
            ->orderByDesc('start_date')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('Payroll/Incidents/Index', [
            'incidents' => $incidents,
            'users' => $this->attendanceUsers(),
            'types' => Incident::TYPES,
            'filters' => $request->only(['user_id', 'type', 'from', 'to']),
        ]);
    }

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

    /**
     * Collaborators with attendance enabled (the only ones with a day record).
     */
    private function attendanceUsers()
    {
        return User::query()
            ->whereHas('payrollProfile', fn ($query) => $query->where('is_attendance_subject', true))
            ->orderBy('name')
            ->get(['id', 'name']);
    }
}
