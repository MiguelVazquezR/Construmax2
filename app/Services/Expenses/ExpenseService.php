<?php

namespace App\Services\Expenses;

use App\Models\Expense;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class ExpenseService
{
    /**
     * Columns that can be sorted from the index (front-end key => database column).
     */
    private const SORTABLE_COLUMNS = [
        'folio' => 'folio',
        'expense_date' => 'expense_date',
        'amount' => 'amount',
        'budget_id' => 'budget_id',
        'status' => 'status',
    ];

    /**
     * Paginated expenses for the index listing.
     */
    public function getFilteredExpenses(array $filters): LengthAwarePaginator
    {
        return $this->query($filters)
            ->paginate(15)
            ->withQueryString()
            ->through(fn (Expense $expense) => $this->mapExpense($expense));
    }

    /**
     * Rows for the Excel export (no pagination), honouring the same filters as the index.
     */
    public function getExportRows(array $filters): Collection
    {
        return $this->query($filters)->get()->map(fn (Expense $expense) => $this->mapExpense($expense));
    }

    private function query(array $filters): Builder
    {
        return $this->applySorting($this->buildQuery($filters), $filters)
            ->with(['category', 'budget.ticket', 'creator', 'media']);
    }

    /**
     * @return array<string, mixed>
     */
    public function mapExpense(Expense $expense): array
    {
        return [
            'id' => $expense->id,
            'folio' => $expense->folio,
            'expense_date' => $expense->expense_date?->format('Y-m-d'),
            'concept' => $expense->concept,
            'reference' => $expense->reference,
            'notes' => $expense->notes,
            'category_id' => $expense->expense_category_id,
            'category_name' => $expense->category?->name,
            'budget_id' => $expense->budget_id,
            'budget_concept_id' => $expense->budget_concept_id,
            'deposit_id' => $expense->deposit_id,
            'budget_folio' => $expense->budget?->ticket?->folio,
            'budget_name' => $expense->budget?->ticket?->name,
            'is_commission' => (bool) $expense->is_commission,
            'amount' => (float) $expense->amount,
            'commission_amount' => (float) ($expense->commission_amount ?? 0),
            'payment_method' => $expense->payment_method,
            'payment_method_label' => $expense->payment_method_label,
            'status' => $expense->status,
            'status_label' => $expense->status_label,
            'created_by' => $expense->creator?->name,
            'receipt_url' => $expense->receipt_url,
            'receipt_name' => $expense->receipt_name,
            'receipts' => $expense->receipts,
            'created_at' => $expense->created_at?->toDateTimeString(),
        ];
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
            ->selectRaw('COALESCE(SUM(amount + COALESCE(commission_amount, 0)), 0) as total_amount')
            ->selectRaw('COALESCE(SUM(CASE WHEN status = ? THEN 1 ELSE 0 END), 0) as pending_count', [Expense::STATUS_PENDING])
            ->selectRaw('COALESCE(SUM(CASE WHEN status = ? THEN amount + COALESCE(commission_amount, 0) ELSE 0 END), 0) as pending_amount', [Expense::STATUS_PENDING])
            ->selectRaw('COALESCE(SUM(CASE WHEN status = ? THEN 1 ELSE 0 END), 0) as paid_count', [Expense::STATUS_PAID])
            ->selectRaw('COALESCE(SUM(CASE WHEN status = ? THEN amount + COALESCE(commission_amount, 0) ELSE 0 END), 0) as paid_amount', [Expense::STATUS_PAID])
            ->selectRaw('COALESCE(SUM(CASE WHEN budget_id IS NOT NULL THEN 1 ELSE 0 END), 0) as budget_count')
            ->selectRaw('COALESCE(SUM(CASE WHEN budget_id IS NOT NULL THEN amount + COALESCE(commission_amount, 0) ELSE 0 END), 0) as budget_amount')
            ->first();

        return [
            'total_count' => (int) $summary->total_count,
            'total_amount' => (float) $summary->total_amount,
            'pending_count' => (int) $summary->pending_count,
            'pending_amount' => (float) $summary->pending_amount,
            'paid_count' => (int) $summary->paid_count,
            'paid_amount' => (float) $summary->paid_amount,
            'budget_count' => (int) $summary->budget_count,
            'budget_amount' => (float) $summary->budget_amount,
        ];
    }

    private function buildQuery(array $filters): Builder
    {
        return Expense::query()
            ->search($filters['search'] ?? null)
            ->withFolio($filters['folio'] ?? null)
            ->withStatus($filters['status'] ?? null)
            ->inCategory($filters['category_id'] ?? null)
            ->withPaymentMethod($filters['payment_method'] ?? null)
            ->ofType($filters['type'] ?? null)
            ->forBudget($filters['budget_id'] ?? null)
            ->betweenDates($filters['from'] ?? null, $filters['to'] ?? null);
    }

    /**
     * Sort the listing, falling back to the newest expenses first.
     */
    private function applySorting(Builder $query, array $filters): Builder
    {
        $column = self::SORTABLE_COLUMNS[$filters['sort_by'] ?? ''] ?? null;

        if (!$column) {
            return $query->orderByDesc('expense_date')->orderByDesc('id');
        }

        $direction = ($filters['sort_dir'] ?? 'asc') === 'desc' ? 'desc' : 'asc';

        // Statuses follow the workflow order: pending -> paid -> cancelled
        if ($column === 'status') {
            return $query
                ->orderByRaw(
                    'CASE status WHEN ? THEN 1 WHEN ? THEN 2 ELSE 3 END ' . $direction,
                    [Expense::STATUS_PENDING, Expense::STATUS_PAID]
                )
                ->orderByDesc('id');
        }

        return $query->orderBy($column, $direction)->orderByDesc('id');
    }
}
