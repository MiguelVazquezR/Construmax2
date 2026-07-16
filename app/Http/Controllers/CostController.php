<?php

namespace App\Http\Controllers;

use App\Actions\Notifications\DispatchNotificationAction;
use App\Http\Controllers\Controller;
use App\Models\Budget;
use App\Services\Costs\CostService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Http\RedirectResponse;

class CostController extends Controller
{
    public function __construct(
        private readonly CostService $costService,
        private readonly DispatchNotificationAction $dispatchNotification,
    ) {}

    public function index(Request $request): Response
    {
        if (!$request->user()->can('costs.index')) {
            abort(403);
        }

        $budgets = $this->costService->getBudgetsForCosting($request->all());

        return Inertia::render('Costs/Index', [
            'budgets' => $budgets,
            'filters' => $request->only(['search', 'catalog', 'branch']),
        ]);
    }

    public function show(Request $request, Budget $budget): Response
    {
        if (!$request->user()->can('costs.index')) {
            abort(403);
        }

        $budgetDetails = $this->costService->getBudgetCatalogDetails($budget);

        return Inertia::render('Costs/Show', [
            'budget' => $budgetDetails,
            'canCreateCatalog' => $request->user()->can('costs.create'),
            'canApprove' => $request->user()->can('costs.approve'),
        ]);
    }

    public function storeCatalog(Request $request, Budget $budget): RedirectResponse
    {
        if (!$request->user()->can('costs.create')) {
            abort(403);
        }

        $validated = $request->validate([
            'subtotal' => 'required|numeric|min:0',
            'iva' => 'required|numeric|min:0',
            'total' => 'required|numeric|min:0',
            'non_installation_labor' => 'nullable|numeric|min:0',
            'labor_utility' => 'nullable|numeric|min:0',
            'items' => 'required|array|min:1',
            'items.*.type' => 'nullable|string|in:material,labor',
            'items.*.description' => 'required|string',
            'items.*.unit' => 'nullable|string',
            'items.*.technician' => 'nullable|string|max:255',
            'items.*.hours' => 'nullable|numeric|min:0',
            'items.*.rate' => 'nullable|numeric|min:0',
            'items.*.quantity' => 'required|numeric|min:0.01',
            'items.*.unit_price' => 'required|numeric|min:0',
            'items.*.total' => 'required|numeric|min:0',
        ]);

        $latestVersion = $budget->latestCatalog ? $budget->latestCatalog->version : 0;

        $catalog = $budget->catalogs()->create([
            'version' => $latestVersion + 1,
            'subtotal' => $validated['subtotal'],
            'iva' => $validated['iva'],
            'total' => $validated['total'],
            'non_installation_labor' => $validated['non_installation_labor'] ?? 0,
            'labor_utility' => $validated['labor_utility'] ?? 0,
            'status' => \App\Models\BudgetCatalog::STATUS_PENDING_APPROVAL,
        ]);

        $catalog->items()->createMany($validated['items']);

        // Update ticket status to Pendiente de aprobación
        $ticket = $budget->ticket;
        if ($ticket) {
            $ticket->update(['status' => 'Pendiente de aprobación']);
        }

        return back()->with('success', 'Nueva versión del catálogo guardada correctamente. Queda pendiente de aprobación.');
    }

    /**
     * Approve a cost catalog.
     */
    public function approveCatalog(Request $request, Budget $budget, \App\Models\BudgetCatalog $catalog): RedirectResponse
    {
        if (!$request->user()->can('costs.approve')) {
            abort(403);
        }

        if ($catalog->isApproved()) {
            return back()->with('info', 'Este catálogo ya fue aprobado anteriormente.');
        }

        $catalog->approve($request->user()->id);

        // Move ticket back to Catálogo status (approved)
        $ticket = $budget->ticket;
        if ($ticket && $ticket->status === 'Pendiente de aprobación') {
            $ticket->update(['status' => 'Catálogo']);
        }

        // Dispatch notification: catalog approved
        $this->dispatchNotification->catalogApproved($catalog);

        return back()->with('success', 'Catálogo de costos aprobado correctamente.');
    }

    public function print(Request $request, Budget $budget): Response
    {
        if (!$request->user()->can('costs.index')) {
            abort(403);
        }

        $budgetDetails = $this->costService->getBudgetCatalogDetails($budget);
        $version = $request->query('version');

        return Inertia::render('Costs/Print', [
            'budget' => $budgetDetails,
            'version' => $version,
        ]);
    }

    public function printEmpenoFacil(Request $request, Budget $budget): Response
    {
        if (!$request->user()->can('costs.index')) {
            abort(403);
        }

        $budgetDetails = $this->costService->getBudgetCatalogDetails($budget);
        $version = $request->query('version');

        return Inertia::render('Costs/PrintEmpenoFacil', [
            'budget' => $budgetDetails,
            'version' => $version,
        ]);
    }
}
