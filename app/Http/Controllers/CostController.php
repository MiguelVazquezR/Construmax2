<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Budget;
use App\Services\Costs\CostService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

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
            'filters' => $request->only(['search', 'status']),
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
}