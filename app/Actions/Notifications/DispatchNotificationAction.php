<?php

namespace App\Actions\Notifications;

use App\Models\Budget;
use App\Models\BudgetCatalog;
use App\Models\Deposit;
use App\Models\NotificationSetting;
use App\Models\Ticket;
use App\Models\User;
use App\Notifications\CatalogApproved;
use App\Notifications\DepositPendingApproval;
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
     * Notify when a cost catalog has been approved.
     * Only the ticket's seller receives this notification, and only if
     * they have the catalog.approved notification type active.
     */
    public function catalogApproved(BudgetCatalog $catalog): void
    {
        $sellerId = $catalog->budget->ticket->seller_id;

        if (! $sellerId) {
            return;
        }

        $isSubscribed = NotificationSetting::where('notification_type', NotificationService::TYPE_CATALOG_APPROVED)
            ->where('user_id', $sellerId)
            ->where('is_active', true)
            ->exists();

        if (! $isSubscribed) {
            return;
        }

        $seller = User::find($sellerId);

        if ($seller && $seller->email) {
            $seller->notify(new CatalogApproved($catalog));
        }
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

    /**
     * Notify when a new deposit is created and needs approval.
     * Sends to all users with the deposits.approve permission.
     */
    public function depositPendingApproval(Deposit $deposit): void
    {
        $this->notificationService->notifySubscribers(
            NotificationService::TYPE_DEPOSIT_PENDING_APPROVAL,
            new DepositPendingApproval($deposit)
        );
    }
}
