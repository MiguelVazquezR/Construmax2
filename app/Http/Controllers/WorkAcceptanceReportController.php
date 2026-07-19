<?php

namespace App\Http\Controllers;

use App\Actions\WorkAcceptanceReports\CreateWorkAcceptanceReportAction;
use App\Actions\WorkAcceptanceReports\SignWorkAcceptanceReportAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\WorkAcceptanceReports\SignWorkAcceptanceReportRequest;
use App\Http\Requests\WorkAcceptanceReports\StoreWorkAcceptanceReportRequest;
use App\Models\Ticket;
use App\Models\WorkAcceptanceReport;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Inertia\Inertia;
use Inertia\Response;

class WorkAcceptanceReportController extends Controller
{
    public function __construct(
        private readonly CreateWorkAcceptanceReportAction $createReportAction,
        private readonly SignWorkAcceptanceReportAction $signReportAction,
    ) {}

    /**
     * Generate or retrieve a work acceptance report for a ticket.
     * Returns back to the ticket page so Inertia refreshes the data.
     */
    public function store(StoreWorkAcceptanceReportRequest $request): RedirectResponse
    {
        $ticket = Ticket::findOrFail($request->validated('ticket_id'));

        $this->createReportAction->execute($ticket);

        return back()->with('success', 'Acta de recepción generada correctamente.');
    }

    /**
     * Display the work acceptance report (print-friendly view, internal).
     */
    public function show(Request $request, WorkAcceptanceReport $report): Response
    {
        $report->load([
            'ticket.customer.media',
            'ticket.contact',
            'ticket.branch',
            'ticket.budget',
            'ticket.tasks.assignee',
            'ticket.tasks.media',
        ]);

        $technicianNames = $this->gatherTechnicianNames($report->ticket);

        return Inertia::render('WorkAcceptanceReports/Show', [
            'report'           => $report,
            'technicianNames'  => $technicianNames,
            'submitUrl'        => route('work-acceptance-reports.sign', $report),
        ]);
    }

    /**
     * Public signed-URL view for the work acceptance report.
     */
    public function publicShow(Request $request, WorkAcceptanceReport $report): Response
    {
        $report->load([
            'ticket.customer.media',
            'ticket.contact',
            'ticket.branch',
            'ticket.budget',
            'ticket.tasks.assignee',
            'ticket.tasks.media',
        ]);

        $technicianNames = $this->gatherTechnicianNames($report->ticket);

        return Inertia::render('WorkAcceptanceReports/Show', [
            'report'           => $report,
            'technicianNames'  => $technicianNames,
            'isPublic'         => true,
            'submitUrl'        => URL::signedRoute('work-acceptance-reports.public.store-signature', [
                'report' => $report->id,
            ]),
        ]);
    }

    /**
     * Process the signature submission (works for both internal and public routes).
     */
    public function storeSignature(SignWorkAcceptanceReportRequest $request, WorkAcceptanceReport $report): Response|RedirectResponse
    {
        if ($report->isSigned()) {
            return back()->with('info', 'Esta acta de recepción ya fue firmada anteriormente.');
        }

        $this->signReportAction->execute($report, $request->validated());

        return back()->with('success', 'Acta de recepción firmada exitosamente.');
    }

    /**
     * Update editable fields of the report (public, via signed URL).
     * Allows technicians to fill in work description, timestamps, and comments.
     */
    public function publicUpdate(Request $request, WorkAcceptanceReport $report): \Illuminate\Http\JsonResponse|RedirectResponse
    {
        if ($report->isSigned()) {
            return response()->json(['message' => 'No se puede editar un acta de recepción ya firmada.'], 403);
        }

        $validated = $request->validate([
            'work_description'    => ['nullable', 'string', 'max:5000'],
            'on_site_start'       => ['nullable', 'date'],
            'on_site_end'         => ['nullable', 'date', 'after_or_equal:on_site_start'],
            'technician_comments' => ['nullable', 'string', 'max:2000'],
        ]);

        // Convert UTC ISO dates from browser to app timezone before storing
        foreach (['on_site_start', 'on_site_end'] as $field) {
            if (!empty($validated[$field])) {
                $validated[$field] = Carbon::parse($validated[$field])
                    ->setTimezone(config('app.timezone'))
                    ->format('Y-m-d H:i:s');
            }
        }

        $report->update($validated);

        return response()->json(['message' => 'Acta de recepción actualizada.']);
    }

    /**
     * Generate a signed URL for the public view, returned as JSON for clipboard copy.
     * Returns JSON when called via AJAX, or redirects with flash for Inertia requests.
     */
    public function generatePublicLink(Request $request, WorkAcceptanceReport $report): RedirectResponse|\Illuminate\Http\JsonResponse
    {
        $url = URL::signedRoute('work-acceptance-reports.public.show', [
            'report' => $report->id,
        ]);

        if ($request->expectsJson()) {
            return response()->json(['url' => $url]);
        }

        return back()->with('publicLink', $url);
    }

    /**
     * Gather unique technician names from the ticket's JSON arrays and tasks.
     */
    private function gatherTechnicianNames(Ticket $ticket): array
    {
        $names = [];

        // From tasks assignees
        foreach ($ticket->tasks as $task) {
            if ($task->assignee) {
                $names[$task->assignee->id] = $task->assignee->name;
            }
        }

        // Also try to load users from technician IDs
        $techIds = array_merge(
            $ticket->technicians ?? [],
            $ticket->assistant_technicians ?? []
        );

        if (!empty($techIds)) {
            $users = \App\Models\User::whereIn('id', $techIds)->pluck('name', 'id');
            foreach ($users as $id => $name) {
                $names[$id] = $name;
            }
        }

        return array_values($names);
    }
}
