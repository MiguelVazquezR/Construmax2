<?php

namespace App\Http\Controllers;

use App\Actions\Notifications\DispatchNotificationAction;
use App\Models\BudgetCatalog;
use App\Services\SpecialCosts\SpecialCostService;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class SpecialCostController extends Controller
{
    public function __construct(
        private readonly SpecialCostService $specialCostService,
        private readonly DispatchNotificationAction $dispatchNotification,
    ) {}

    public function index(Request $request): Response
    {
        if (!$request->user()->can('special-costs.index')) {
            abort(403);
        }

        $catalogs = $this->specialCostService->getPendingCatalogs($request->all());

        return Inertia::render('SpecialCosts/Index', [
            'catalogs' => $catalogs,
            'filters'  => $request->only(['search', 'branch']),
            'canTransfer' => $request->user()->can('costs.transfer'),
            'canApprove'  => $request->user()->can('special-costs.approve'),
            'canCreateVersion' => $request->user()->can('special-costs.create-version'),
        ]);
    }

    public function show(Request $request, BudgetCatalog $catalog): Response
    {
        if (!$request->user()->can('special-costs.index')) {
            abort(403);
        }

        // Ensure this catalog actually needs special authorization
        if (!$catalog->needs_special_authorization) {
            abort(404);
        }

        $details = $this->specialCostService->getCatalogDetails($catalog);

        return Inertia::render('SpecialCosts/Show', [
            'catalog'          => $details['catalog'],
            'budget'           => $details['budget'],
            'ticket'           => $details['ticket'],
            'latest_catalog'   => $details['latest_catalog'],
            'catalogs'         => $details['catalogs'],
            'concepts'         => $details['concepts'],
            'task_evidence'    => $details['task_evidence'],
            'ticket_media'     => $details['ticket_media'],
            'canCreateCatalog' => $request->user()->can('special-costs.create-version'),
            'canApprove'       => $request->user()->can('special-costs.approve'),
        ]);
    }

    /**
     * Create a new catalog version from the special costs module.
     */
    public function storeCatalog(Request $request, BudgetCatalog $catalog): RedirectResponse
    {
        if (!$request->user()->can('special-costs.create-version')) {
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

        $budget = $catalog->budget;
        $latestVersion = $budget->latestCatalog ? $budget->latestCatalog->version : 0;

        $newCatalog = $budget->catalogs()->create([
            'version'                    => $latestVersion + 1,
            'subtotal'                   => $validated['subtotal'],
            'iva'                        => $validated['iva'],
            'total'                      => $validated['total'],
            'non_installation_labor'     => $validated['non_installation_labor'] ?? 0,
            'labor_utility'              => $validated['labor_utility'] ?? 0,
            'status'                     => BudgetCatalog::STATUS_PENDING_APPROVAL,
            'needs_special_authorization' => true,
            'transfer_notes'             => $catalog->transfer_notes, // Preserve original transfer notes
        ]);

        $newCatalog->items()->createMany($validated['items']);

        // Clear the flag on the previous catalog so it no longer appears in the list
        $catalog->update(['needs_special_authorization' => false]);

        return redirect()->route('special-costs.show', $newCatalog->id)
            ->with('success', 'Nueva versión del catálogo creada correctamente. Pendiente de aprobación.');
    }

    /**
     * Approve a special cost catalog.
     */
    public function approveCatalog(Request $request, BudgetCatalog $catalog): RedirectResponse
    {
        if (!$request->user()->can('special-costs.approve')) {
            abort(403);
        }

        if ($catalog->isApproved()) {
            return back()->with('info', 'Este catálogo ya fue aprobado anteriormente.');
        }

        $catalog->approve($request->user()->id);

        // Move ticket to Catálogo status (same behavior as regular costs approval)
        $budget = $catalog->budget;
        if ($budget->ticket_id) {
            $ticket = \App\Models\Ticket::find($budget->ticket_id);
            if ($ticket && $ticket->status === 'Pendiente de aprobación') {
                $ticket->update(['status' => 'Catálogo']);
            }
        }

        // Dispatch notification: catalog approved (same as regular costs)
        $this->dispatchNotification->catalogApproved($catalog);

        return redirect()->route('special-costs.index')
            ->with('success', 'Catálogo de costos especiales aprobado correctamente.');
    }
}