<?php

namespace App\Http\Controllers;

use App\Actions\Expenses\CreateExpenseAction;
use App\Actions\Expenses\DeleteExpenseAction;
use App\Actions\Expenses\MarkExpensePaidAction;
use App\Actions\Expenses\UpdateExpenseAction;
use App\Http\Requests\Expenses\StoreExpenseRequest;
use App\Http\Requests\Expenses\UpdateExpenseRequest;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Ticket;
use App\Services\Expenses\ExpenseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ExpenseController extends Controller
{
    public function __construct(
        private readonly ExpenseService $expenseService,
        private readonly CreateExpenseAction $createExpenseAction,
        private readonly UpdateExpenseAction $updateExpenseAction,
        private readonly DeleteExpenseAction $deleteExpenseAction,
        private readonly MarkExpensePaidAction $markExpensePaidAction,
    ) {}

    public function index(Request $request): Response
    {
        if (!$request->user()->can('expenses.index')) {
            abort(403);
        }

        $filters = $request->only(['search', 'status', 'category_id', 'from', 'to']);

        return Inertia::render('Expenses/Index', [
            'expenses' => $this->expenseService->getFilteredExpenses($filters),
            'stats' => $this->expenseService->getSummary($filters),
            'categories' => ExpenseCategory::active()->orderBy('name')->get(['id', 'name']),
            'filters' => $filters,
        ]);
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

        $this->deleteExpenseAction->execute($expense);

        return back()->with('success', 'Gasto eliminado correctamente.');
    }

    public function markPaid(Request $request, Expense $expense): RedirectResponse
    {
        if (!$request->user()->can('expenses.edit')) {
            abort(403);
        }

        $this->markExpensePaidAction->execute($expense);

        return back()->with('success', 'Gasto marcado como pagado.');
    }

    /**
     * Remote search of tickets for the optional project link in the expense form.
     */
    public function searchTickets(Request $request): JsonResponse
    {
        if (!$request->user()->can('expenses.create') && !$request->user()->can('expenses.edit')) {
            abort(403);
        }

        $term = trim((string) $request->input('q'));

        $tickets = Ticket::query()
            ->with(['customer:id,name'])
            ->when($term !== '', function ($query) use ($term) {
                $query->where(function ($query) use ($term) {
                    $query->where('name', 'like', "%{$term}%")
                        ->orWhereHas('customer', fn ($q) => $q->where('name', 'like', "%{$term}%"));

                    if (is_numeric($term)) {
                        $query->orWhere('id', (int) $term);
                    }
                });
            })
            ->orderByDesc('id')
            ->limit(20)
            ->get()
            ->map(fn (Ticket $ticket) => [
                'id' => $ticket->id,
                'folio' => $ticket->folio,
                'name' => $ticket->name,
                'customer_name' => $ticket->customer?->name,
            ]);

        return response()->json($tickets);
    }
}
