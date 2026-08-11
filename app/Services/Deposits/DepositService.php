<?php

namespace App\Services\Deposits;

use App\Models\Budget;
use App\Models\Technician;
use App\Models\TechnicianPayment;
use App\Models\Ticket;

class DepositService
{
    /**
     * Calculate the pending amount a technician can still receive from a budget.
     *
     * pool_total = SUM(budget_concepts.amount) WHERE paid_to_technician = true
     * already_paid = SUM(technician_payments.amount) FOR this budget + this technician
     * pending = pool_total - already_paid
     */
    public function pendingAmountForTechnician(Technician $technician, Budget $budget): float
    {
        $poolTotal = $budget->concepts()
            ->where('paid_to_technician', true)
            ->sum('amount');

        $alreadyPaid = TechnicianPayment::where('budget_id', $budget->id)
            ->where('user_id', $technician->user_id)
            ->sum('amount');

        return max(0, $poolTotal - $alreadyPaid);
    }

    /**
     * Get tickets where the technician participated AND there is still a pending balance.
     */
    public function pendingTicketsForTechnician(Technician $technician)
    {
        return Ticket::whereInvolved($technician->user_id)
            ->whereHas('budget')
            ->with('budget', 'customer')
            ->get()
            ->filter(fn ($ticket) => $this->pendingAmountForTechnician($technician, $ticket->budget) > 0)
            ->values();
    }

    /**
     * Default shift based on server time: before 3pm → matutino, after → vespertino.
     */
    public function defaultShift(): string
    {
        return now()->hour >= 15 ? 'vespertino' : 'matutino';
    }
}
