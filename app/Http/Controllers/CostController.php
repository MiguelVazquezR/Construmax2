<?php

namespace App\Http\Controllers;

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
        ]);
    }

    public function storeCatalog(Request $request, Budget $budget): RedirectResponse
    {
        if (!$request->user()->can('costs.index')) {
            abort(403);
        }

        $validated = $request->validate([
            'subtotal' => 'required|numeric|min:0',
            'iva' => 'required|numeric|min:0',
            'total' => 'required|numeric|min:0',
            'items' => 'required|array|min:1',
            'items.*.description' => 'required|string',
            'items.*.unit' => 'required|string',
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
        ]);

        $catalog->items()->createMany($validated['items']);

        return back()->with('success', 'Nueva versión del catálogo guardada correctamente.');
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
}
