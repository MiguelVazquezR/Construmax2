<?php

namespace App\Console\Commands;

use App\Actions\Notifications\DispatchNotificationAction;
use App\Models\Budget;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class CheckOverdueInvoices extends Command
{
    protected $signature = 'notifications:check-overdue-invoices';
    protected $description = 'Check for invoices whose payment due date is today and dispatch notifications';

    public function __construct(
        private readonly DispatchNotificationAction $dispatchNotification,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        // Find Facturado tickets that have an invoice_date and are not yet paid
        $budgets = Budget::with(['ticket.customer'])
            ->whereHas('ticket', fn ($q) => $q->where('status', 'Facturado'))
            ->whereNotNull('invoice_date')
            ->get();

        $today = now()->startOfDay();
        $notified = 0;

        foreach ($budgets as $budget) {
            $ticket = $budget->ticket;
            $paymentDays = $ticket->customer->payment_days ?? 0;

            if ($paymentDays <= 0 || !$budget->invoice_date) {
                continue;
            }

            $dueDate = $budget->invoice_date->copy()->addDays($paymentDays)->startOfDay();

            // Only notify exactly on the due date, not before and not after
            if ($dueDate->equalTo($today)) {
                $this->dispatchNotification->invoiceOverdue($ticket, $budget);
                $this->info("Overdue notification dispatched for ticket {$ticket->folio} (due: {$dueDate->toDateString()})");
                $notified++;
            }
        }

        $this->info("Checked {$budgets->count()} invoiced tickets. {$notified} notification(s) sent.");
        Log::info("Revisar facturas vencidas: {$notified} notification(es) enviadas de {$budgets->count()} vencimientos.");

        return self::SUCCESS;
    }
}
