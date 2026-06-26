<?php

namespace App\Services\Invoices;

use App\Models\Budget;
use Illuminate\Pagination\LengthAwarePaginator;

class InvoiceService
{
    public function getPendingInvoices(array $filters): LengthAwarePaginator
    {
        return Budget::with(['ticket.customer', 'ticket.tasks.media'])
            ->whereHas('ticket', function ($q) {
                $q->whereIn('status', ['Finalizado', 'Facturado']);
            })
            ->when($filters['search'] ?? null, function ($query, $search) {
                $query->whereHas('ticket', function ($q) use ($search) {
                    $q->where('name', 'like', '%' . $search . '%')
                      ->orWhereHas('customer', function ($q) use ($search) {
                          $q->where('name', 'like', '%' . $search . '%');
                      });
                });
            })
            ->when($filters['status'] ?? null, function ($query, $status) {
                if ($status !== 'all') {
                    $query->whereHas('ticket', fn($q) => $q->where('status', $status));
                }
            })
            ->orderBy('created_at', 'desc')
            ->paginate(15)
            ->through(function ($budget) {
               $paymentDays = $budget->ticket->customer->payment_days ?? 0;
                $dueDate = $budget->invoice_date
                    ? $budget->invoice_date->copy()->addDays($paymentDays)->format('Y-m-d')
                    : null;

                // Recopilar evidencias de todas las tareas del ticket
                $taskEvidence = collect($budget->ticket->tasks ?? [])
                    ->filter(fn($task) => $task->relationLoaded('media') && $task->media->isNotEmpty())
                    ->flatMap(function ($task) {
                        return $task->media->map(function ($media) use ($task) {
                            return [
                                'id'          => $media->id,
                                'url'         => $media->getUrl(),
                                'file_name'   => $media->file_name,
                                'task_name'   => $task->name,
                                'task_status' => $task->status,
                            ];
                        });
                    })
                    ->values()
                    ->toArray();

                return [
                    'id'               => $budget->id,
                    'ticket_name'      => $budget->ticket->name ?? 'N/A',
                    'ticket_folio'     => $budget->ticket->folio ?? 'N/A',
                    'ticket_id'        => $budget->ticket->id ?? null,
                    'customer_name'    => $budget->ticket->customer->name ?? 'N/A',
                    'status'           => $budget->ticket->status ?? $budget->status,
                    'total_cost'       => $budget->total_cost,
                    'currency'         => $budget->currency,
                    'invoice_date'     => $budget->invoice_date?->format('Y-m-d'),
                    'invoice_number'   => $budget->invoice_number,
                    'due_date'         => $dueDate,
                    'payment_days'     => $paymentDays,
                    'has_invoice_file' => $budget->hasMedia('invoice_document'),
                    'invoice_url'      => $budget->getFirstMediaUrl('invoice_document'),
                    'task_evidence'    => $taskEvidence,
                ];
            });
    }
}