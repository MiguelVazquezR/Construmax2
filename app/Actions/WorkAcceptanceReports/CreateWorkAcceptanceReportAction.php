<?php

namespace App\Actions\WorkAcceptanceReports;

use App\Models\Ticket;
use App\Models\WorkAcceptanceReport;

class CreateWorkAcceptanceReportAction
{
    /**
     * Generate or retrieve a Work Acceptance Report for a ticket.
     * If one already exists, return it. Otherwise, create a new one
     * auto-populated with all available data from the database.
     */
    public function execute(Ticket $ticket): WorkAcceptanceReport
    {
        // If a report already exists for this ticket, return it
        $existing = WorkAcceptanceReport::where('ticket_id', $ticket->id)->first();
        if ($existing) {
            return $existing;
        }

        // Pre-fill with available technician-entered data from tasks
        $tasks = $ticket->tasks;

        // Get earliest start and latest end from tasks
        $onSiteStart = $tasks->whereNotNull('start_date')->min('start_date');
        $onSiteEnd = $tasks->whereNotNull('due_date')->max('due_date');

        // Gather technician comments
        $technicianComments = $tasks
            ->whereNotNull('technician_notes')
            ->map(fn($t) => "[{$t->name}] {$t->technician_notes}")
            ->implode("\n");

        return WorkAcceptanceReport::create([
            'ticket_id'           => $ticket->id,
            'report_date'         => now()->toDateString(),
            'work_description'    => null, // El técnico debe llenarlo manualmente
            'on_site_start'       => $onSiteStart,
            'on_site_end'         => $onSiteEnd,
            'technician_comments' => $technicianComments ?: null,
            'created_by'          => \Illuminate\Support\Facades\Auth::id() ?? $ticket->seller_id ?? 1,
        ]);
    }
}
