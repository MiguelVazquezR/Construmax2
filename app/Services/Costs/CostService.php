<?php

namespace App\Services\Costs;

use App\Models\Budget;
use Illuminate\Pagination\LengthAwarePaginator;

class CostService
{
    public function getBudgetsForCosting(array $filters): LengthAwarePaginator
    {
        return Budget::with(['ticket.customer', 'ticket.branch', 'ticket.contact', 'latestCatalog.approver'])
            ->when($filters['search'] ?? null, function ($query, $search) {
                $query->whereHas('ticket', function ($q) use ($search) {
                    $q->where('name', 'like', '%' . $search . '%')
                        ->orWhere('folio', 'like', '%' . $search . '%')
                        ->orWhereHas('customer', function ($q) use ($search) {
                            $q->where('name', 'like', '%' . $search . '%');
                        });
                });
            })
            ->when($filters['catalog'] ?? 'pending', function ($query, $catalogStatus) {
                if ($catalogStatus === 'with') {
                    $query->has('catalogs');
                } elseif ($catalogStatus === 'without') {
                    $query->doesntHave('catalogs');
                } elseif ($catalogStatus === 'pending') {
                    $query->whereHas('latestCatalog', function ($q) {
                        $q->where('status', \App\Models\BudgetCatalog::STATUS_PENDING_APPROVAL);
                    });
                } elseif ($catalogStatus === 'approved') {
                    $query->whereHas('latestCatalog', function ($q) {
                        $q->where('status', \App\Models\BudgetCatalog::STATUS_APPROVED);
                    });
                }
            })
            ->when($filters['branch'] ?? null, function ($query, $branch) {
                $query->whereHas('ticket.branch', function ($q) use ($branch) {
                    $q->where('branch_name', 'like', '%' . $branch . '%')
                        ->orWhere('unit', 'like', '%' . $branch . '%')
                        ->orWhere('country', 'like', '%' . $branch . '%')
                        ->orWhere('region', 'like', '%' . $branch . '%');
                });
            })
            ->orderBy('created_at', 'desc')
            ->paginate(15)
            ->through(function ($budget) {
                return [
                    'id'               => $budget->id,
                    'ticket_name'      => $budget->ticket->name ?? 'N/A',
                    'ticket_folio'     => $budget->ticket->folio ?? 'N/A',
                    'customer_name'    => $budget->ticket->customer->name ?? 'N/A',
                    'status'           => $budget->ticket->status ?? 'N/A',
                    'total_cost'       => $budget->latestCatalog
                        ? $budget->latestCatalog->total
                        : $budget->total_cost,
                    'currency'         => $budget->currency,
                    'concept_count'    => $budget->concepts()->count(),
                    'latest_version'   => $budget->latestCatalog ? $budget->latestCatalog->version : null,
                    'has_catalog'      => $budget->latestCatalog !== null,
                    'catalog_status'   => $budget->latestCatalog ? $budget->latestCatalog->status : null,
                    'catalog_status_label' => $budget->latestCatalog ? $budget->latestCatalog->statusLabel() : null,
                    'catalog_approved_by' => $budget->latestCatalog?->approver?->name ?? null,
                    'catalog_id'       => $budget->latestCatalog?->id ?? null,
                    'branch_name'      => $budget->ticket->branch->branch_name ?? '—',
                    'branch_unit'      => $budget->ticket->branch->unit ?? '—',
                    'branch_country'   => $budget->ticket->branch->country ?? '—',
                    'branch_region'    => $budget->ticket->branch->region ?? '—',
                    'contact_name'     => $budget->ticket->contact->name ?? '—',
                ];
            });
    }

    public function getBudgetCatalogDetails(Budget $budget): array
    {
        $budget->load([
            'ticket.customer',
            'ticket.branch',
            'ticket.contact',
            'ticket.seller',
            'ticket.tasks.media',
            'ticket.media',
            'concepts',
            'catalogs.items',
            'catalogs.approver',
            'latestCatalog.items',
            'latestCatalog.approver',
            'media',
        ]);

        // Resolve technician user IDs to user+technician data
        $techIds = array_merge(
            array_map('intval', $budget->ticket->technicians ?? []),
            array_map('intval', $budget->ticket->assistant_technicians ?? [])
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
        $taskEvidence = $budget->ticket->tasks->flatMap(function ($task) {
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

        $ticketMedia = $budget->ticket->media->map(function ($media) {
            return [
                'id'          => $media->id,
                'file_name'   => $media->file_name,
                'mime_type'   => $media->mime_type,
                'url'         => $media->getUrl(),
                'created_at'  => $media->created_at?->toISOString(),
            ];
        })->values();

        return [
            'id'            => $budget->id,
            'status'        => $budget->ticket->status ?? 'N/A',
            'currency'      => $budget->currency,
            'exchange_rate' => $budget->exchange_rate,
            'description'   => $budget->description,
            'ticket'        => [
                'id'              => $budget->ticket->id ?? null,
                'folio'           => $budget->ticket->folio ?? 'N/A',
                'name'            => $budget->ticket->name ?? 'N/A',
                'report_number'   => $budget->ticket->report_number ?? null,
                'service_type'    => $budget->ticket->service_type ?? 'N/A',
                'scheduled_start' => $budget->ticket->scheduled_start ?? null,
                'scheduled_end'   => $budget->ticket->scheduled_end ?? null,
                'instructions'    => $budget->ticket->instructions ?? null,
                'customer'        => [
                    'id'   => $budget->ticket->customer->id ?? null,
                    'name' => $budget->ticket->customer->name ?? 'N/A',
                    'rfc'  => $budget->ticket->customer->rfc ?? 'N/A',
                ],
                'contact'         => [
                    'name'  => $budget->ticket->contact->name ?? 'N/A',
                    'phone' => $budget->ticket->contact->phone ?? 'N/A',
                    'email' => $budget->ticket->contact->email ?? 'N/A',
                ],
                'branch'          => [
                    'branch_name' => $budget->ticket->branch->branch_name ?? 'N/D',
                    'region'      => $budget->ticket->branch->region ?? 'N/D',
                    'country'     => $budget->ticket->branch->country ?? 'N/D',
                    'city'        => $budget->ticket->branch->city ?? 'N/D',
                    'unit'        => $budget->ticket->branch->unit ?? 'N/D',
                ],
                'seller'          => $budget->ticket->seller ? [
                    'name'  => $budget->ticket->seller->name,
                    'email' => $budget->ticket->seller->email,
                ] : null,
                'technicians'     => $technicians,
                'technician_ids'  => array_map('intval', $budget->ticket->technicians ?? []),
                'assistant_technician_ids' => array_map('intval', $budget->ticket->assistant_technicians ?? []),
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
            'catalogs'       => $budget->catalogs->map(function ($catalog) {
                return [
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
                    'approved_by_name' => $catalog->approver?->name ?? null,
                    'items'   => $catalog->items->map(function ($item) {
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
            'concepts'       => $budget->concepts->map(function ($concept) {
                return [
                    'id'      => $concept->id,
                    'concept' => $concept->concept,
                    'amount'  => $concept->amount,
                ];
            }),
            'survey_images'  => $budget->getMedia('survey_images')->map(function ($media) {
                return [
                    'id'   => $media->id,
                    'name' => $media->file_name,
                    'url'  => $media->getUrl(),
                ];
            }),
            'task_evidence'  => $taskEvidence,
            'ticket_media'   => $ticketMedia,
            'subtotal'       => $budget->total_cost,
        ];
    }
}
