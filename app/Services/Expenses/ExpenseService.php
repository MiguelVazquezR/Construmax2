<?php

namespace App\Services\Expenses;

use App\Models\Expense;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

class ExpenseService
{
    /**
     * Paginated expenses for the index listing.
     */
    public function getFilteredExpenses(array $filters): LengthAwarePaginator
    {
        return $this->buildQuery($filters)
            ->with(['category', 'ticket', 'creator', 'media'])
            ->orderByDesc('expense_date')
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (Expense $expense) => [
                'id' => $expense->id,
                'folio' => $expense->folio,
                'expense_date' => $expense->expense_date?->format('Y-m-d'),
                'concept' => $expense->concept,
                'reference' => $expense->reference,
                'notes' => $expense->notes,
                'category_id' => $expense->expense_category_id,
                'category_name' => $expense->category?->name,
                'ticket_id' => $expense->ticket_id,
                'ticket_name' => $expense->ticket?->name,
                'ticket_folio' => $expense->ticket?->folio,
                'amount' => (float) $expense->amount,
                'payment_method' => $expense->payment_method,
                'payment_method_label' => $expense->payment_method_label,
                'status' => $expense->status,
                'status_label' => $expense->status_label,
                'created_by' => $expense->creator?->name,
                'receipt_url' => $expense->receipt_url,
                'receipt_name' => $expense->receipt_name,
                'created_at' => $expense->created_at?->toDateTimeString(),
            ]);
    }

    /**
     * Summary totals for the current filter scope.
     *
     * The status filter is intentionally excluded so every status card keeps
     * showing its totals while the user browses a specific status.
     *
     * @return array<string, int|float>
     */
    public function getSummary(array $filters): array
    {
        unset($filters['status']);

        $summary = $this->buildQuery($filters)
            ->selectRaw('COUNT(*) as total_count')
            ->selectRaw('COALESCE(SUM(amount), 0) as total_amount')
            ->selectRaw('COALESCE(SUM(CASE WHEN status = ? THEN 1 ELSE 0 END), 0) as pending_count', [Expense::STATUS_PENDING])
            ->selectRaw('COALESCE(SUM(CASE WHEN status = ? THEN amount ELSE 0 END), 0) as pending_amount', [Expense::STATUS_PENDING])
            ->selectRaw('COALESCE(SUM(CASE WHEN status = ? THEN 1 ELSE 0 END), 0) as paid_count', [Expense::STATUS_PAID])
            ->selectRaw('COALESCE(SUM(CASE WHEN status = ? THEN amount ELSE 0 END), 0) as paid_amount', [Expense::STATUS_PAID])
            ->selectRaw('COALESCE(SUM(CASE WHEN ticket_id IS NOT NULL THEN 1 ELSE 0 END), 0) as project_count')
            ->selectRaw('COALESCE(SUM(CASE WHEN ticket_id IS NOT NULL THEN amount ELSE 0 END), 0) as project_amount')
            ->first();

        return [
            'total_count' => (int) $summary->total_count,
            'total_amount' => (float) $summary->total_amount,
            'pending_count' => (int) $summary->pending_count,
            'pending_amount' => (float) $summary->pending_amount,
            'paid_count' => (int) $summary->paid_count,
            'paid_amount' => (float) $summary->paid_amount,
            'project_count' => (int) $summary->project_count,
            'project_amount' => (float) $summary->project_amount,
        ];
    }

    private function buildQuery(array $filters): Builder
    {
        return Expense::query()
            ->search($filters['search'] ?? null)
            ->withStatus($filters['status'] ?? null)
            ->inCategory($filters['category_id'] ?? null)
            ->betweenDates($filters['from'] ?? null, $filters['to'] ?? null);
    }
}
