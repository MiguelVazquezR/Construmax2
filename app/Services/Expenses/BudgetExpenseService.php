<?php

namespace App\Services\Expenses;

use App\Models\Budget;
use App\Models\BudgetConcept;
use App\Models\Expense;

class BudgetExpenseService
{
    public function __construct(
        private readonly ExpenseService $expenseService,
    ) {}

    /**
     * Lightweight budget search for the expense form picker.
     *
     * @return array<int, array<string, mixed>>
     */
    public function searchBudgets(?string $term, ?string $status, int $limit = 30): array
    {
        $term = trim((string) $term);

        return Budget::query()
            ->with(['ticket.customer:id,name'])
            ->whereHas('ticket')
            ->when($term !== '', function ($query) use ($term) {
                $query->where(function ($query) use ($term) {
                    $query->whereHas('ticket', fn ($ticket) => $ticket->where('name', 'like', "%{$term}%"))
                        ->orWhereHas('ticket.customer', fn ($customer) => $customer->where('name', 'like', "%{$term}%"));

                    // Match the numeric part of a folio (e.g. "#5-MEX")
                    $digits = preg_replace('/\D/', '', $term);

                    if ($digits !== '') {
                        $query->orWhereHas('ticket', fn ($ticket) => $ticket->where('id', (int) $digits));
                    }
                });
            })
            ->when($status, fn ($query, $status) => $query->whereHas('ticket', fn ($ticket) => $ticket->where('status', $status)))
            ->orderByDesc('id')
            ->limit($limit)
            ->get()
            ->map(fn (Budget $budget) => [
                'id' => $budget->id,
                'folio' => $budget->ticket?->folio,
                'name' => $budget->ticket?->name,
                'customer_name' => $budget->ticket?->customer?->name,
                'status' => $budget->ticket?->status,
            ])
            ->values()
            ->all();
    }

    /**
     * Budget data with its breakdown, registered payments, extras and totals.
     *
     * @return array<string, mixed>
     */
    public function getPanelData(Budget $budget): array
    {
        $budget->load([
            'ticket.customer',
            'concepts.expense.category',
            'concepts.expense.media',
            'expenses.category',
            'expenses.media',
        ]);

        // Expenses are mapped with their budget already set so no extra queries run.
        $budget->expenses->each(fn (Expense $expense) => $expense->setRelation('budget', $budget));
        $budget->concepts->each(function (BudgetConcept $concept) use ($budget) {
            if ($concept->expense) {
                $concept->expense->setRelation('budget', $budget);
            }
        });

        $conceptExpenses = $budget->expenses->whereNotNull('budget_concept_id');
        $extras = $budget->expenses->whereNull('budget_concept_id')->values();

        $conceptsTotal = (float) $budget->concepts->sum('amount');
        $extrasTotal = (float) $extras->sum(fn (Expense $expense) => $this->expenseTotal($expense));
        $paidTotal = (float) $budget->expenses
            ->where('status', Expense::STATUS_PAID)
            ->sum(fn (Expense $expense) => $this->expenseTotal($expense));
        $paidConceptBaseTotal = (float) $conceptExpenses
            ->where('status', Expense::STATUS_PAID)
            ->sum(fn (Expense $expense) => (float) $expense->amount);
        $pendingExtrasTotal = (float) $extras
            ->where('status', Expense::STATUS_PENDING)
            ->sum(fn (Expense $expense) => $this->expenseTotal($expense));

        return [
            'id' => $budget->id,
            'folio' => $budget->ticket?->folio,
            'name' => $budget->ticket?->name,
            'customer_name' => $budget->ticket?->customer?->name,
            'status' => $budget->ticket?->status,
            'currency' => $budget->currency,
            'description' => $budget->description,
            'concepts' => $budget->concepts
                ->map(fn (BudgetConcept $concept) => [
                    'id' => $concept->id,
                    'concept' => $concept->concept,
                    'amount' => (float) $concept->amount,
                    'paid_to_technician' => (bool) $concept->paid_to_technician,
                    'payment_date' => $concept->payment_date?->format('Y-m-d'),
                    'expense' => $concept->expense ? $this->expenseService->mapExpense($concept->expense) : null,
                ])
                ->values(),
            'extras' => $extras
                ->map(fn (Expense $expense) => $this->expenseService->mapExpense($expense))
                ->values(),
            'totals' => [
                'concepts' => $conceptsTotal,
                'extras' => $extrasTotal,
                'paid' => $paidTotal,
                'pending' => $conceptsTotal - $paidConceptBaseTotal + $pendingExtrasTotal,
            ],
        ];
    }

    /**
     * Money actually paid for an expense: amount plus the payment channel commission.
     */
    private function expenseTotal(Expense $expense): float
    {
        return (float) $expense->amount + (float) ($expense->commission_amount ?? 0);
    }
}
