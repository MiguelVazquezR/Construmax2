<?php

namespace App\Http\Controllers;

use App\Actions\Deposits\CompleteDepositAction;
use App\Actions\Expenses\CreateExpenseAction;
use App\Actions\Expenses\DeleteExpenseAction;
use App\Actions\Expenses\MarkExpensePaidAction;
use App\Actions\Expenses\UpdateExpenseAction;
use App\Http\Requests\Expenses\CompleteDepositFromExpenseRequest;
use App\Http\Requests\Expenses\StoreExpenseRequest;
use App\Http\Requests\Expenses\UpdateExpenseRequest;
use App\Models\Budget;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Services\Expenses\ExpenseService;
use App\Services\Export\XlsxWriterService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ExpenseController extends Controller
{
    public function __construct(
        private readonly ExpenseService $expenseService,
        private readonly XlsxWriterService $xlsxWriter,
        private readonly CreateExpenseAction $createExpenseAction,
        private readonly UpdateExpenseAction $updateExpenseAction,
        private readonly DeleteExpenseAction $deleteExpenseAction,
        private readonly MarkExpensePaidAction $markExpensePaidAction,
        private readonly CompleteDepositAction $completeDepositAction,
    ) {}

    public function index(Request $request): Response
    {
        if (!$request->user()->can('expenses.index')) {
            abort(403);
        }

        $filters = $this->indexFilters($request);

        return Inertia::render('Expenses/Index', [
            'expenses' => $this->expenseService->getFilteredExpenses($filters),
            'stats' => $this->expenseService->getSummary($filters),
            'categories' => ExpenseCategory::active()->orderBy('name')->get(['id', 'name']),
            'budgets' => $this->budgetsWithExpenses(),
            'filters' => $filters,
        ]);
    }

    /**
     * Excel report with the same filters that are applied on the index.
     */
    public function export(Request $request): BinaryFileResponse
    {
        if (!$request->user()->can('expenses.index')) {
            abort(403);
        }

        $rows = $this->expenseService->getExportRows($this->indexFilters($request));

        $headers = [
            'Folio',
            'Fecha',
            'Concepto',
            'Referencia',
            'Categoría',
            'Presupuesto',
            'Método de pago',
            'Estatus',
            'Monto',
            'Comisión',
            'Comprobante',
            'Registró',
            'Notas',
        ];

        $data = $rows->map(fn (array $row) => [
            $row['folio'],
            $row['expense_date'],
            $row['concept'],
            $row['reference'],
            // Budget concept payments carry no category of their own: fall back
            // to the cost type of the concept (Mano de obra / Materiales).
            $row['category_name'] ?? match ($row['budget_concept_type'] ?? null) {
                'labor' => 'Mano de obra',
                'material' => 'Materiales',
                default => null,
            },
            trim(($row['budget_folio'] ?? '') . ' ' . ($row['budget_name'] ?? '')),
            $row['payment_method_label'],
            $row['status_label'],
            (float) $row['amount'],
            ($row['commission_amount'] ?? 0) > 0 ? (float) $row['commission_amount'] : null,
            $row['receipt_name'] ? 'Sí' : 'No',
            $row['created_by'],
            $row['notes'],
        ])->all();

        // Totals: base amounts and base + commission, overall / paid / pending.
        $sum = function (?string $status) use ($rows): array {
            $filtered = $status === null ? $rows : $rows->where('status', $status);

            return [
                'amount' => (float) $filtered->sum('amount'),
                'commission' => (float) $filtered->sum('commission_amount'),
            ];
        };

        $total = $sum(null);
        $paid = $sum(Expense::STATUS_PAID);
        $pending = $sum(Expense::STATUS_PENDING);

        $path = $this->xlsxWriter->generate(
            'Gastos',
            $headers,
            $data,
            [10, 12, 42, 18, 24, 30, 18, 34, 14, 12, 14, 22, 30],
            [
                ['', '', '', '', '', '', '', 'Total', $total['amount'], '', '', '', ''],
                ['', '', '', '', '', '', '', 'Total + comisión', $total['amount'] + $total['commission'], '', '', '', ''],
                ['', '', '', '', '', '', '', 'Total pagado', $paid['amount'], '', '', '', ''],
                ['', '', '', '', '', '', '', 'Total pagado + comisión', $paid['amount'] + $paid['commission'], '', '', '', ''],
                ['', '', '', '', '', '', '', 'Total pendiente de pago', $pending['amount'], '', '', '', ''],
                ['', '', '', '', '', '', '', 'Total pendiente de pago + comisión', $pending['amount'] + $pending['commission'], '', '', '', ''],
            ],
        );

        return response()
            ->download($path, 'reporte-gastos-' . now()->format('Y-m-d') . '.xlsx', [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ])
            ->deleteFileAfterSend();
    }

    /**
     * Filters shared by the index and the Excel report.
     *
     * @return array<string, string|null>
     */
    private function indexFilters(Request $request): array
    {
        return $request->only([
            'search',
            'folio',
            'status',
            'category_id',
            'payment_method',
            'type',
            'budget_id',
            'from',
            'to',
            'sort_by',
            'sort_dir',
        ]);
    }

    /**
     * Budgets that already have expenses, used by the index budget filter.
     */
    private function budgetsWithExpenses()
    {
        return Budget::query()
            ->whereHas('expenses')
            ->with(['ticket.customer:id,name'])
            ->orderByDesc('id')
            ->get()
            ->map(fn (Budget $budget) => [
                'id' => $budget->id,
                'folio' => $budget->ticket?->folio,
                'name' => $budget->ticket?->name,
                'customer_name' => $budget->ticket?->customer?->name,
            ])
            ->values();
    }

    public function store(StoreExpenseRequest $request): RedirectResponse
    {
        $this->createExpenseAction->execute($request->validated());

        return back()->with('success', 'Gasto registrado correctamente.');
    }

    public function update(UpdateExpenseRequest $request, Expense $expense): RedirectResponse
    {
        $this->updateExpenseAction->execute($expense, $request->validated());

        return back()->with('success', 'Gasto actualizado correctamente.');
    }

    public function destroy(Request $request, Expense $expense): RedirectResponse
    {
        if (!$request->user()->can('expenses.delete')) {
            abort(403);
        }

        // Deposit expenses are a mirror of the deposits module.
        if ($expense->deposit_id) {
            return back()->with('error', 'Este gasto proviene de un depósito: elimínalo desde el módulo de depósitos.');
        }

        $this->deleteExpenseAction->execute($expense);

        return back()->with('success', 'Gasto eliminado correctamente.');
    }

    public function markPaid(Request $request, Expense $expense): RedirectResponse
    {
        if (!$request->user()->can('expenses.edit')) {
            abort(403);
        }

        // Deposits are completed with their voucher and commission from the
        // "Marcar realizado" flow instead of the quick mark-as-paid action.
        if ($expense->deposit_id) {
            return back()->with('error', 'Este gasto proviene de un depósito: usa "Marcar realizado" para subir el comprobante y la comisión.');
        }

        $this->markExpensePaidAction->execute($expense);

        return back()->with('success', 'Gasto marcado como pagado.');
    }

    /**
     * Complete the deposit linked to an expense: stores the voucher and the
     * commission, and creates the technician payment (both modules stay in sync).
     */
    public function completeDeposit(CompleteDepositFromExpenseRequest $request, Expense $expense): RedirectResponse
    {
        $deposit = $expense->deposit;

        if (!$deposit) {
            abort(404);
        }

        if ($deposit->status === 'completed') {
            return back()->with('error', 'Este depósito ya fue marcado como realizado.');
        }

        $this->completeDepositAction->execute($deposit, [
            'voucher' => $request->file('voucher'),
            'commission_amount' => $request->validated('commission_amount'),
        ]);

        return back()->with('success', 'Depósito marcado como realizado. El gasto quedó actualizado.');
    }
}
