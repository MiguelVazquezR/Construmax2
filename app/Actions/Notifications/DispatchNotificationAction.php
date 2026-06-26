<?php

namespace App\Actions\Notifications;

use App\Models\Budget;
use App\Models\BudgetCatalog;
use App\Models\Ticket;
use App\Notifications\CatalogCreated;
use App\Notifications\InvoiceOverdue;
use App\Notifications\TicketNeedsCatalog;
use App\Notifications\TicketNeedsInvoice;
use App\Services\Notifications\NotificationService;

class DispatchNotificationAction
{
    public function __construct(
        private readonly NotificationService $notificationService,
    ) {}

    /**
     * Notify when a ticket needs a cost catalog.
     */
    public function ticketNeedsCatalog(Ticket $ticket): void
    {
        $this->notificationService->notifySubscribers(
            NotificationService::TYPE_TICKET_NEEDS_CATALOG,
            new TicketNeedsCatalog($ticket)
        );
    }

    /**
     * Notify when a new cost catalog has been created.
     */
    public function catalogCreated(BudgetCatalog $catalog): void
    {
        $this->notificationService->notifySubscribers(
            NotificationService::TYPE_CATALOG_CREATED,
            new CatalogCreated($catalog)
        );
    }

    /**
     * Notify when a ticket needs an invoice (status = Finalizado).
     */
    public function ticketNeedsInvoice(Ticket $ticket): void
    {
        $this->notificationService->notifySubscribers(
            NotificationService::TYPE_TICKET_NEEDS_INVOICE,
            new TicketNeedsInvoice($ticket)
        );
    }

    /**
     * Notify when an invoice is overdue (due date reached today).
     */
    public function invoiceOverdue(Ticket $ticket, Budget $budget): void
    {
        $this->notificationService->notifySubscribers(
            NotificationService::TYPE_INVOICE_OVERDUE,
            new InvoiceOverdue($ticket, $budget)
        );
    }
}
