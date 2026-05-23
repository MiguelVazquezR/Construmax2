<?php

namespace App\Services\Costs;

use App\Models\Budget;
use Illuminate\Pagination\LengthAwarePaginator;

class CostService
{
    public function getBudgetsForCosting(array $filters): LengthAwarePaginator
    {
        return Budget::with(['ticket.customer'])
            ->when($filters['search'] ?? null, function ($query, $search) {
                $query->whereHas('ticket', function ($q) use ($search) {
                    $q->where('name', 'like', '%' . $search . '%')
                      ->orWhereHas('customer', function ($q) use ($search) {
                          $q->where('name', 'like', '%' . $search . '%');
                      });
                });
            })
            ->when($filters['status'] ?? 'all', function ($query, $status) {
                if ($status !== 'all') {
                    $query->where('status', $status);
                } else {
                    $query->where('status', '!=', 'Perdido');
                }
            })
            ->orderBy('created_at', 'desc')
            ->paginate(15)
            ->through(function ($budget) {
                return [
                    'id'            => $budget->id,
                    'ticket_name'   => $budget->ticket->name ?? 'N/A',
                    'ticket_folio'  => $budget->ticket->folio ?? 'N/A',
                    'customer_name' => $budget->ticket->customer->name ?? 'N/A',
                    'status'        => $budget->status,
                    'total_cost'    => $budget->total_cost,
                    'currency'      => $budget->currency,
                    'concept_count' => $budget->concepts()->count(),
                ];
            });
    }

    public function getBudgetCatalogDetails(Budget $budget): array
    {
        $budget->load(['ticket.customer', 'ticket.branch', 'concepts']);

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
            'concepts'      => $budget->concepts->map(function ($concept) {
                return [
                    'id'      => $concept->id,
                    'concept' => $concept->concept,
                    'amount'  => $concept->amount,
                ];
            }),
            'subtotal'      => $budget->total_cost,
        ];
    }
}