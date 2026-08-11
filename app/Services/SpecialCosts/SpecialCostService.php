<?php

namespace App\Services\SpecialCosts;

use App\Models\BudgetCatalog;
use Illuminate\Pagination\LengthAwarePaginator;

class SpecialCostService
{
    /**
     * Get catalogs that need special authorization and are still pending approval.
     */
    public function getPendingCatalogs(array $filters): LengthAwarePaginator
    {
        return BudgetCatalog::with(['budget.ticket.customer', 'budget.ticket.branch', 'approver'])
            ->where('needs_special_authorization', true)
            ->where('status', BudgetCatalog::STATUS_PENDING_APPROVAL)
            ->when($filters['search'] ?? null, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->whereHas('budget.ticket', function ($sub) use ($search) {
                        $sub->where('name', 'like', '%' . $search . '%')
                            ->orWhereHas('customer', function ($sub2) use ($search) {
                                $sub2->where('name', 'like', '%' . $search . '%');
                            });
                    });
                });
            })
            ->when($filters['branch'] ?? null, function ($query, $branch) {
                $query->whereHas('budget.ticket.branch', function ($q) use ($branch) {
                    $q->where('branch_name', 'like', '%' . $branch . '%')
                        ->orWhere('unit', 'like', '%' . $branch . '%')
                        ->orWhere('country', 'like', '%' . $branch . '%')
                        ->orWhere('region', 'like', '%' . $branch . '%');
                });
            })
            ->orderBy('created_at', 'desc')
            ->paginate(15)
            ->through(function ($catalog) {
                return [
                    'id'          => $catalog->id,
                    'budget_id'   => $catalog->budget_id,
                    'version'     => $catalog->version,
                    'subtotal'    => $catalog->subtotal,
                    'iva'         => $catalog->iva,
                    'total'       => $catalog->total,
                    'status'      => $catalog->status,
                    'status_label' => $catalog->statusLabel(),
                    'needs_special_authorization' => $catalog->needs_special_authorization,
                    'transfer_notes' => $catalog->transfer_notes,
                    'is_approved' => $catalog->isApproved(),
                    'approved_by_name' => $catalog->approver?->name ?? null,
                    'created_at'  => $catalog->created_at?->toISOString(),
                    'ticket_id'   => $catalog->budget->ticket->id ?? null,
                    'ticket_folio' => $catalog->budget->ticket->folio ?? 'N/A',
                    'ticket_name' => $catalog->budget->ticket->name ?? 'N/A',
                    'customer_name' => $catalog->budget->ticket->customer->name ?? 'N/A',
                    'branch_name' => $catalog->budget->ticket->branch->branch_name ?? '—',
                    'branch_unit' => $catalog->budget->ticket->branch->unit ?? '—',
                    'ticket_important_note' => $catalog->budget->ticket->important_note ?? null,
                ];
            });
    }

    /**
     * Load catalog details for the special costs show view.
     * Reuses the same structure as CostService::getBudgetCatalogDetails().
     */
    public function getCatalogDetails(BudgetCatalog $catalog): array
    {
        $catalog->load([
            'items',
            'approver',
            'budget.ticket.customer',
            'budget.ticket.branch',
            'budget.ticket.contact',
            'budget.ticket.seller',
            'budget.ticket.tasks.media',
            'budget.ticket.media',
            'budget.concepts',
            'budget.latestCatalog.items',
            'budget.latestCatalog.approver',
            'budget.catalogs.items',
            'budget.catalogs.approver',
            'budget.media',
        ]);

        $budget = $catalog->budget;
        $ticket = $budget->ticket;

        // Resolve technician user IDs to user+technician data
        $techIds = array_merge(
            array_map('intval', $ticket->technicians ?? []),
            array_map('intval', $ticket->assistant_technicians ?? [])
        );
        $technicians = collect();
        if (!empty($techIds)) {
            $technicians = \App\Models\User::whereIn('id', $techIds)
                ->with('technician')
                ->get()
                ->map(function ($user) {
                    return [
                        'id'               => $user->id,
                        'name'             => $user->name,
                        'email'            => $user->email,
                        'profile_photo_url' => $user->profile_photo_url,
                        'phone'            => $user->technician->phone ?? '',
                        'level'            => $user->technician->level ?? 'Encargado',
                        'status'           => $user->technician->status ?? 'N/A',
                        'rating_avg'       => $user->technician->rating_avg ?? 0,
                    ];
                });
        }

        // Collect all task evidence (media) across all tasks
        $taskEvidence = $ticket->tasks->flatMap(function ($task) {
            return $task->media->map(function ($media) use ($task) {
                return [
                    'id'          => $media->id,
                    'task_name'   => $task->name,
                    'task_status' => $task->status,
                    'file_name'   => $media->file_name,
                    'mime_type'   => $media->mime_type,
                    'url'         => $media->getUrl(),
                    'created_at'  => $media->created_at?->toISOString(),
                ];
            });
        })->sortByDesc('created_at')->values();

        $ticketMedia = $ticket->media->map(function ($media) {
            return [
                'id'          => $media->id,
                'file_name'   => $media->file_name,
                'mime_type'   => $media->mime_type,
                'url'         => $media->getUrl(),
                'created_at'  => $media->created_at?->toISOString(),
            ];
        })->values();

        return [
            'catalog' => [
                'id'      => $catalog->id,
                'version' => $catalog->version,
                'subtotal' => $catalog->subtotal,
                'iva'      => $catalog->iva,
                'total'    => $catalog->total,
                'non_installation_labor' => $catalog->non_installation_labor,
                'labor_utility'         => $catalog->labor_utility,
                'status'   => $catalog->status,
                'status_label' => $catalog->statusLabel(),
                'is_approved' => $catalog->isApproved(),
                'needs_special_authorization' => $catalog->needs_special_authorization,
                'transfer_notes' => $catalog->transfer_notes,
                'approved_by_name' => $catalog->approver?->name ?? null,
                'items'    => $catalog->items->map(function ($item) {
                    return [
                        'id'          => $item->id,
                        'type'        => $item->type,
                        'description' => $item->description,
                        'unit'        => $item->unit,
                        'technician'  => $item->technician,
                        'hours'       => $item->hours,
                        'rate'        => $item->rate,
                        'quantity'    => $item->quantity,
                        'unit_price'  => $item->unit_price,
                        'total'       => $item->total,
                    ];
                }),
            ],
            'budget' => [
                'id'            => $budget->id,
                'currency'      => $budget->currency,
                'exchange_rate' => $budget->exchange_rate,
                'description'   => $budget->description,
                'subtotal'      => $budget->total_cost,
            ],
            'ticket' => [
                'id'              => $ticket->id ?? null,
                'folio'           => $ticket->folio ?? 'N/A',
                'name'            => $ticket->name ?? 'N/A',
                'report_number'   => $ticket->report_number ?? null,
                'service_type'    => $ticket->service_type ?? 'N/A',
                'status'          => $ticket->status ?? 'N/A',
                'scheduled_start' => $ticket->scheduled_start ?? null,
                'scheduled_end'   => $ticket->scheduled_end ?? null,
                'instructions'    => $ticket->instructions ?? null,
                'important_note'  => $ticket->important_note ?? null,
                'customer'        => [
                    'id'   => $ticket->customer->id ?? null,
                    'name' => $ticket->customer->name ?? 'N/A',
                    'rfc'  => $ticket->customer->rfc ?? 'N/A',
                ],
                'contact'         => [
                    'name'  => $ticket->contact->name ?? 'N/A',
                    'phone' => $ticket->contact->phone ?? 'N/A',
                    'email' => $ticket->contact->email ?? 'N/A',
                ],
                'branch'          => [
                    'branch_name' => $ticket->branch->branch_name ?? 'N/D',
                    'region'      => $ticket->branch->region ?? 'N/D',
                    'country'     => $ticket->branch->country ?? 'N/D',
                    'city'        => $ticket->branch->city ?? 'N/D',
                    'unit'        => $ticket->branch->unit ?? 'N/D',
                ],
                'seller'          => $ticket->seller ? [
                    'name'  => $ticket->seller->name,
                    'email' => $ticket->seller->email,
                ] : null,
                'technicians'     => $technicians,
                'technician_ids'  => array_map('intval', $ticket->technicians ?? []),
                'assistant_technician_ids' => array_map('intval', $ticket->assistant_technicians ?? []),
            ],
            'latest_catalog' => $budget->latestCatalog ? [
                'id'      => $budget->latestCatalog->id,
                'version' => $budget->latestCatalog->version,
                'subtotal' => $budget->latestCatalog->subtotal,
                'iva'      => $budget->latestCatalog->iva,
                'total'    => $budget->latestCatalog->total,
                'non_installation_labor' => $budget->latestCatalog->non_installation_labor,
                'labor_utility'         => $budget->latestCatalog->labor_utility,
                'status'   => $budget->latestCatalog->status,
                'status_label' => $budget->latestCatalog->statusLabel(),
                'is_approved' => $budget->latestCatalog->isApproved(),
                'needs_special_authorization' => $budget->latestCatalog->needs_special_authorization,
                'transfer_notes' => $budget->latestCatalog->transfer_notes,
                'approved_by_name' => $budget->latestCatalog->approver?->name ?? null,
                'items'    => $budget->latestCatalog->items->map(function ($item) {
                    return [
                        'id'          => $item->id,
                        'type'        => $item->type,
                        'description' => $item->description,
                        'unit'        => $item->unit,
                        'technician'  => $item->technician,
                        'hours'       => $item->hours,
                        'rate'        => $item->rate,
                        'quantity'    => $item->quantity,
                        'unit_price'  => $item->unit_price,
                        'total'       => $item->total,
                    ];
                }),
            ] : null,
            'catalogs' => $budget->catalogs->map(function ($cat) {
                return [
                    'id'      => $cat->id,
                    'version' => $cat->version,
                    'subtotal' => $cat->subtotal,
                    'iva'      => $cat->iva,
                    'total'    => $cat->total,
                    'non_installation_labor' => $cat->non_installation_labor,
                    'labor_utility'         => $cat->labor_utility,
                    'status'   => $cat->status,
                    'status_label' => $cat->statusLabel(),
                    'is_approved' => $cat->isApproved(),
                    'needs_special_authorization' => $cat->needs_special_authorization,
                    'transfer_notes' => $cat->transfer_notes,
                    'approved_by_name' => $cat->approver?->name ?? null,
                    'items'   => $cat->items->map(function ($item) {
                        return [
                            'id'          => $item->id,
                            'type'        => $item->type,
                            'description' => $item->description,
                            'unit'        => $item->unit,
                            'technician'  => $item->technician,
                            'hours'       => $item->hours,
                            'rate'        => $item->rate,
                            'quantity'    => $item->quantity,
                            'unit_price'  => $item->unit_price,
                            'total'       => $item->total,
                        ];
                    }),
                ];
            }),
            'concepts' => $budget->concepts->map(function ($concept) {
                return [
                    'id'      => $concept->id,
                    'concept' => $concept->concept,
                    'amount'  => $concept->amount,
                ];
            }),
            'task_evidence'  => $taskEvidence,
            'ticket_media'   => $ticketMedia,
        ];
    }
}