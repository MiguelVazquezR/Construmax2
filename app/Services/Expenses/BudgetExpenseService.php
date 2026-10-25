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
     * Budgets matching the expense picker filters, with their breakdown
     * concepts and payment state so payments can be registered in bulk.
     *
     * @param array<int, string> $statuses Ticket statuses to include
     * @return array<int, array<string, mixed>>
     */
    public function searchBudgets(
        ?string $term,
        array $statuses = [],
        ?string $from = null,
        ?string $to = null,
        bool $onlyPending = false,
        int $limit = 100,
    ): array {
        $term = trim((string) $term);
        $statuses = array_values(array_filter($statuses));

        return Budget::query()
            ->with([
                'ticket.customer:id,name',
                'concepts' => fn ($query) => $query->orderBy('id'),
                'concepts.expense',
            ])
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
            ->when($statuses !== [], fn ($query) => $query->whereHas('ticket', fn ($ticket) => $ticket->whereIn('status', $statuses)))
            // The date range matches the moment the ticket moved to its current status.
            ->when($from, fn ($query, $from) => $query->whereHas('ticket', fn ($ticket) => $ticket->whereDate('status_changed_at', '>=', $from)))
            ->when($to, fn ($query, $to) => $query->whereHas('ticket', fn ($ticket) => $ticket->whereDate('status_changed_at', '<=', $to)))
            ->when($onlyPending, fn ($query) => $query->whereHas('concepts', fn ($concept) => $concept
                ->whereDoesntHave('expense', fn ($expense) => $expense->where('status', Expense::STATUS_PAID))))
            ->orderByDesc('id')
            ->limit($limit)
            ->get()
            ->map(function (Budget $budget) {
                $concepts = $budget->concepts->map(function (BudgetConcept $concept) use ($budget) {
                    $expense = $concept->expense;

                    return [
                        'id' => $concept->id,
                        'concept' => $concept->concept,
                        'amount' => (float) $concept->amount,
                        'type' => $concept->type,
                        'is_paid' => $expense?->status === Expense::STATUS_PAID,
                        'payment_date' => $concept->payment_date?->format('Y-m-d'),
                        'expense' => $expense ? [
                            'id' => $expense->id,
                            'folio' => $expense->folio,
                            'status' => $expense->status,
                            'status_label' => $expense->status_label,
                            'expense_date' => $expense->expense_date?->format('Y-m-d'),
                            'payment_method' => $expense->payment_method,
                            'payment_method_label' => $expense->payment_method_label,
                            'category_id' => $expense->expense_category_id,
                            'commission_amount' => (float) ($expense->commission_amount ?? 0),
                            'reference' => $expense->reference,
                            'notes' => $expense->notes,
                        ] : null,
                    ];
                });

                $pending = $concepts->reject(fn (array $concept) => $concept['is_paid']);

                return [
                    'id' => $budget->id,
                    'folio' => $budget->ticket?->folio,
                    'name' => $budget->ticket?->name,
                    'customer_name' => $budget->ticket?->customer?->name,
                    'status' => $budget->ticket?->status,
                    'currency' => $budget->currency,
                    'date' => $budget->created_at?->format('Y-m-d'),
                    'status_date' => $budget->ticket?->status_changed_at?->format('Y-m-d'),
                    'has_pending' => $pending->isNotEmpty(),
                    'pending_count' => $pending->count(),
                    'pending_total' => (float) $pending->sum('amount'),
                    'concepts' => $concepts->values()->all(),
                ];
            })
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
                    'type' => $concept->type,
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
