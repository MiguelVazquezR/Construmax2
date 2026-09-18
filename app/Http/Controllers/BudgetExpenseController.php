<?php

namespace App\Http\Controllers;

use App\Actions\Expenses\MarkBudgetConceptsPaidAction;
use App\Actions\Expenses\RegisterBudgetConceptPaymentAction;
use App\Http\Requests\Expenses\MarkBudgetConceptsPaidRequest;
use App\Http\Requests\Expenses\RegisterBudgetConceptPaymentRequest;
use App\Models\Budget;
use App\Models\BudgetConcept;
use App\Models\ExpenseCategory;
use App\Services\Expenses\BudgetExpenseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BudgetExpenseController extends Controller
{
    public function __construct(
        private readonly BudgetExpenseService $budgetExpenseService,
        private readonly RegisterBudgetConceptPaymentAction $registerConceptPaymentAction,
        private readonly MarkBudgetConceptsPaidAction $markConceptsPaidAction,
    ) {}

    /**
     * Remote search of budgets for the expense form picker.
     */
    public function search(Request $request): JsonResponse
    {
        if (!$request->user()->can('expenses.create') && !$request->user()->can('expenses.edit')) {
            abort(403);
        }

        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'string', 'max:100'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        return response()->json(
            $this->budgetExpenseService->searchBudgets(
                $validated['q'] ?? null,
                $validated['status'] ?? null,
                $validated['limit'] ?? 30,
            )
        );
    }

    /**
     * Manage the expenses of a budget: breakdown payments, extras and commissions.
     */
    public function show(Request $request, Budget $budget): Response
    {
        if (!$request->user()->can('expenses.index')) {
            abort(403);
        }

        return Inertia::render('Expenses/BudgetExpenses', [
            'budget' => $this->budgetExpenseService->getPanelData($budget),
            'categories' => ExpenseCategory::active()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    /**
     * Create or update the payment of a breakdown concept.
     */
    public function storeConceptPayment(RegisterBudgetConceptPaymentRequest $request, Budget $budget, BudgetConcept $concept): RedirectResponse
    {
        if ($concept->budget_id !== $budget->id) {
            abort(404);
        }

        $this->registerConceptPaymentAction->execute($concept, $request->validated());

        return back()->with('success', 'Pago del concepto registrado correctamente.');
    }

    /**
     * Register the payment of several breakdown concepts at once.
     */
    public function markConceptsPaid(MarkBudgetConceptsPaidRequest $request, Budget $budget): RedirectResponse
    {
        $count = $this->markConceptsPaidAction->execute($budget, $request->validated());

        return back()->with('success', $count === 1
            ? 'Se registró el pago de 1 concepto.'
            : "Se registró el pago de {$count} conceptos.");
    }
}
