<?php

namespace App\Services\Costs;

use App\Models\Budget;
use Illuminate\Pagination\LengthAwarePaginator;

class CostService
{
    public function getBudgetsForCosting(array $filters): LengthAwarePaginator
    {
        return Budget::with(['ticket.customer', 'ticket.branch', 'ticket.contact', 'latestCatalog'])
            ->when($filters['search'] ?? null, function ($query, $search) {
                $query->whereHas('ticket', function ($q) use ($search) {
                    $q->where('name', 'like', '%' . $search . '%')
                        ->orWhere('folio', 'like', '%' . $search . '%')
                        ->orWhereHas('customer', function ($q) use ($search) {
                            $q->where('name', 'like', '%' . $search . '%');
                        });
                });
            })
            ->when($filters['catalog'] ?? 'all', function ($query, $catalogStatus) {
                if ($catalogStatus === 'with') {
                    $query->has('catalogs');
                } elseif ($catalogStatus === 'without') {
                    $query->doesntHave('catalogs');
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
                    'id'             => $budget->id,
                    'ticket_name'    => $budget->ticket->name ?? 'N/A',
                    'ticket_folio'   => $budget->ticket->folio ?? 'N/A',
                    'customer_name'  => $budget->ticket->customer->name ?? 'N/A',
                    'status'         => $budget->ticket->status ?? 'N/A',
                    'total_cost'     => $budget->latestCatalog
                        ? $budget->latestCatalog->total
                        : $budget->total_cost,
                    'currency'       => $budget->currency,
                    'concept_count'  => $budget->concepts()->count(),
                    'latest_version' => $budget->latestCatalog ? $budget->latestCatalog->version : null,
                    'has_catalog'    => $budget->latestCatalog !== null,
                    'branch_name'    => $budget->ticket->branch->branch_name ?? '—',
                    'branch_unit'    => $budget->ticket->branch->unit ?? '—',
                    'branch_country' => $budget->ticket->branch->country ?? '—',
                    'branch_region'  => $budget->ticket->branch->region ?? '—',
                    'contact_name'   => $budget->ticket->contact->name ?? '—',
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
            'concepts',
            'catalogs.items',
            'latestCatalog.items',
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
                'service_type'    => $budget->ticket->service_type ?? 'N/A',
                'scheduled_start' => $budget->ticket->scheduled_start ?? null,
                'scheduled_end'   => $budget->ticket->scheduled_end ?? null,
                'instructions'    => $budget->ticket->instructions ?? null,
                'customer'        => [
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
            'latest_catalog' => $budget->latestCatalog,
            'catalogs'       => $budget->catalogs,
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
            'subtotal'       => $budget->total_cost,
        ];
    }
}
