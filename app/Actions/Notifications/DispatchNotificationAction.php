<?php

namespace App\Actions\Notifications;

use App\Models\Budget;
use App\Models\BudgetCatalog;
use App\Models\Deposit;
use App\Models\NotificationSetting;
use App\Models\PayrollPeriod;
use App\Models\Ticket;
use App\Models\User;
use App\Models\VacationPeriod;
use App\Models\VacationRequest;
use App\Notifications\CatalogApproved;
use App\Notifications\CatalogNeedsUpdate;
use App\Notifications\DepositPendingApproval;
use App\Notifications\InvoiceOverdue;
use App\Notifications\PayrollPeriodClosed;
use App\Notifications\TicketNeedsCatalog;
use App\Notifications\TicketNeedsInvoice;
use App\Notifications\VacationPremiumDue;
use App\Notifications\VacationRequested;
use App\Notifications\VacationReviewed;
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
     * Notify the costs team when an edited budget invalidates its catalog and
     * a new version must be saved (catalog status = pending_update).
     * Recipients: active users with the costs.receive-catalog-update-notifications permission.
     */
    public function catalogNeedsUpdate(BudgetCatalog $catalog): void
    {
        $this->notificationService->notifyUsersWithPermissions(
            ['costs.receive-catalog-update-notifications'],
            new CatalogNeedsUpdate($catalog)
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

    /**
     * Notify the approvers (payroll.vacations.approve) about a new vacation request.
     */
    public function vacationRequested(VacationRequest $vacationRequest): void
    {
        $this->notificationService->notifyUsersWithPermissions(
            ['payroll.vacations.approve'],
            new VacationRequested($vacationRequest)
        );
    }

    /**
     * Notify the requester about the review outcome.
     */
    public function vacationReviewed(VacationRequest $vacationRequest): void
    {
        $requester = $vacationRequest->user;

        if (! $requester || ! $requester->email) {
            return;
        }

        $requester->notify(new VacationReviewed(
            $vacationRequest,
            $vacationRequest->status === VacationRequest::STATUS_APPROVED,
        ));
    }

    /**
     * Notify the subscribers when a payroll period is closed automatically.
     */
    public function payrollPeriodClosed(PayrollPeriod $period, int $payslipsCount): void
    {
        $this->notificationService->notifySubscribers(
            NotificationService::TYPE_PAYROLL_PERIOD_CLOSED,
            new PayrollPeriodClosed($period, $payslipsCount)
        );
    }

    /**
     * Notify the subscribers when a collaborator completes a year of service
     * inside the current payroll period and the premium must be paid.
     */
    public function vacationPremiumDue(User $collaborator, VacationPeriod $vacationPeriod, PayrollPeriod $payrollPeriod): void
    {
        $this->notificationService->notifySubscribers(
            NotificationService::TYPE_PAYROLL_VACATION_PREMIUM,
            new VacationPremiumDue($collaborator, $vacationPeriod, $payrollPeriod)
        );
    }
}
