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
                    'status'         => $budget->status,
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
        $budget->load(['ticket.customer', 'ticket.branch', 'concepts', 'catalogs.items', 'latestCatalog.items', 'media']);

        return [
            'id'            => $budget->id,
            'status'        => $budget->status,
            'currency'      => $budget->currency,
            'exchange_rate' => $budget->exchange_rate,
            'description'   => $budget->description,
            'ticket'        => [
                'id'           => $budget->ticket->id ?? null,
                'folio'        => $budget->ticket->folio ?? 'N/A',
                'name'         => $budget->ticket->name ?? 'N/A',
                'service_type' => $budget->ticket->service_type ?? 'N/A',
            ],
            'customer'      => [
                'name' => $budget->ticket->customer->name ?? 'N/A',
            ],
            'latest_catalog' => $budget->latestCatalog,
            'catalogs'      => $budget->catalogs,
            'concepts'      => $budget->concepts->map(function ($concept) {
                return [
                    'id'      => $concept->id,
                    'concept' => $concept->concept,
                    'amount'  => $concept->amount,
                ];
            }),
            'survey_images' => $budget->getMedia('survey_images')->map(function ($media) {
                return [
                    'id'   => $media->id,
                    'name' => $media->file_name,
                    'url'  => $media->getUrl(),
                ];
            }),
            'subtotal'      => $budget->total_cost,
        ];
    }
}
