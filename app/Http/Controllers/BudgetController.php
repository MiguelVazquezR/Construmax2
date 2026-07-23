<?php

namespace App\Http\Controllers;

use App\Models\Budget;
use App\Models\BudgetPayment;
use App\Models\Calendar;
use App\Models\Customer;
use App\Models\Technician;
use App\Models\Ticket;
use App\Models\User;
use App\Models\TechnicianPayment;
use App\Services\Media\ImageOptimizerService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class BudgetController extends Controller
{
    public function __construct(
        private readonly ImageOptimizerService $imageOptimizer,
    ) {}

    public function index(Request $request)
    {
        $perPage = $request->input('perPage', 10);
        
        $filters = $request->only(['search', 'status', 'perPage', 'user_id', 'branch']);

        if (!$request->has('user_id') && !$request->has('search') && !$request->has('status') && !$request->has('page') && !$request->has('branch')) {
            $filters['user_id'] = [auth()->id()];
        }

        return Inertia::render('Budgets/Index', [
            'budgets' => Budget::with(['ticket.customer.media', 'ticket.branch', 'responsible', 'latestCatalog', 'concepts'])
                ->filter($filters)
                ->orderBy('id', 'desc')
                ->paginate($perPage)
                ->withQueryString(),
            'filters' => $filters,
            'users' => User::where('is_active', true)->whereDoesntHave('technician')->orderBy('name')->get(['id', 'name']),
            'customers' => Customer::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function create(Request $request)
    {
        $tickets = Ticket::with(['customer', 'contact', 'branch'])
            ->where(function ($query) use ($request) {
                $query->whereDoesntHave('budget');
                // Always include the requested ticket even if it already has a budget
                if ($request->filled('ticket_id')) {
                    $query->orWhere('id', $request->input('ticket_id'));
                }
            })
            ->orderBy('id', 'desc')
            ->get();

        $technicians = Technician::with('user')->get();

        // Embed technician info into each ticket for direct use in the frontend
        $tickets->each(function ($ticket) use ($technicians) {
            $ticket->technicians_data = collect($ticket->technicians ?? [])->map(function ($userId) use ($technicians) {
                $technician = $technicians->firstWhere('user_id', $userId);
                return [
                    'name' => $technician?->user?->name ?? null,
                    'is_internal' => $technician?->is_internal,
                    'phone' => $technician?->phone,
                    'state' => $technician?->state,
                ];
            });
        });

        return Inertia::render('Budgets/Create', [
            'tickets' => $tickets,
            'users' => User::where('is_active', true)->get(),
            'technicians' => $technicians,
            'preselectedTicketId' => $request->input('ticket_id') ? (int) $request->input('ticket_id') : null,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'ticket_id' => 'required|exists:tickets,id',
            'description' => 'nullable|string',
            'currency' => 'required|string|in:MXN,USD',
            'exchange_rate' => 'required|numeric|min:0.0001',
            'user_id' => 'required|exists:users,id',
            'concepts' => 'array|min:1',
            'concepts.*.concept' => 'required|string',
            'concepts.*.amount' => 'required|numeric|min:0',
            'concepts.*.paid_to_technician' => 'nullable|boolean',
            'concepts.*.payment_date' => 'nullable|date',
            'survey_images' => 'nullable|array',
            'survey_images.*' => 'image|max:10240',
            'support_files' => 'nullable|array',
            'support_files.*' => 'file|max:10240',
        ]);

        $budget = null;

        DB::transaction(function () use ($validated, &$budget) {
            $budget = Budget::create([
                'ticket_id' => $validated['ticket_id'],
                'description' => $validated['description'],
                'currency' => $validated['currency'],
                'exchange_rate' => $validated['exchange_rate'],
                'user_id' => $validated['user_id'],
            ]);

            $budget->concepts()->createMany($validated['concepts']);
        });

        // When a budget is created, move the ticket to 'Catálogo' status
        // to indicate it needs a cost catalog
        $budget?->load('ticket');
        if ($budget->ticket && $budget->ticket->status !== 'Catálogo') {
            $budget->ticket->update(['status' => 'Catálogo']);
        }

        if ($request->hasFile('survey_images')) {
            foreach ($request->file('survey_images') as $image) {
                $optimizedPath = $this->imageOptimizer->optimize($image);
                $budget?->addMedia($optimizedPath)
                    ->usingFileName($image->getClientOriginalName())
                    ->toMediaCollection('survey_images');
            }
        }

        if ($request->hasFile('support_files')) {
            foreach ($request->file('support_files') as $file) {
                if (str_starts_with($file->getMimeType(), 'image/')) {
                    $optimizedPath = $this->imageOptimizer->optimize($file);
                    $budget?->addMedia($optimizedPath)
                        ->usingFileName($file->getClientOriginalName())
                        ->toMediaCollection('budget_files');
                } else {
                    $budget?->addMedia($file)
                        ->usingFileName($file->getClientOriginalName())
                        ->toMediaCollection('budget_files');
                }
            }
        }

        if ($request->boolean('quick_create')) {
            return response()->json([
                'budget' => $budget?->load('ticket.customer'),
                'message' => 'Presupuesto creado exitosamente.'
            ], 201);
        }

        return redirect()->route('budgets.show', $budget->id)->with('success', 'Presupuesto actualizado correctamente.');
    }

    public function show(Budget $budget)
    {
        $budget->load([
            'ticket.customer',
            'ticket.contact',
            'ticket.branch',
            'responsible',
            'concepts',
            'payments.media',
            'media',
            'ticket.tasks.assignee.technician',
            'technicianPayments.media',
            'technicianPayments.technician',
            'latestCatalog',
        ]);

        $budget->append(['total_cost', 'total_paid', 'balance_due', 'total_catalog_cost']);

        return Inertia::render('Budgets/Show', [
            'budget' => $budget,
        ]);
    }

    public function edit(Budget $budget)
    {
        $budget->load(['concepts', 'ticket.customer', 'ticket.contact', 'ticket.branch']);

        $tickets = Ticket::with(['customer', 'contact', 'branch'])
            ->where(function ($q) use ($budget) {
                $q->whereDoesntHave('budget')
                  ->orWhere('id', $budget->ticket_id);
            })
            ->orderBy('id', 'desc')
            ->get();

        $technicians = Technician::with('user')->get();

        // Embed technician info into each ticket for direct use in the frontend
        $tickets->each(function ($ticket) use ($technicians) {
            $ticket->technicians_data = collect($ticket->technicians ?? [])->map(function ($userId) use ($technicians) {
                $technician = $technicians->firstWhere('user_id', $userId);
                return [
                    'name' => $technician?->user?->name ?? null,
                    'is_internal' => $technician?->is_internal,
                    'phone' => $technician?->phone,
                    'state' => $technician?->state,
                ];
            });
        });

        return Inertia::render('Budgets/Edit', [
            'budget' => $budget,
            'tickets' => $tickets,
            'users' => User::where('is_active', true)->get(),
            'technicians' => $technicians,
        ]);
    }

    public function update(Request $request, Budget $budget)
    {
        $validated = $request->validate([
            'ticket_id' => 'required|exists:tickets,id',
            'description' => 'nullable|string',
            'currency' => 'required|string|in:MXN,USD',
            'exchange_rate' => 'required|numeric|min:0.0001',
            'user_id' => 'required|exists:users,id',
            'concepts' => 'array|min:1',
            'concepts.*.concept' => 'required|string',
            'concepts.*.amount' => 'required|numeric|min:0',
            'concepts.*.paid_to_technician' => 'nullable|boolean',
            'concepts.*.payment_date' => 'nullable|date',
            'survey_images' => 'nullable|array',
            'survey_images.*' => 'image|max:10240',
            'support_files' => 'nullable|array',
            'support_files.*' => 'file|max:10240',
        ]);

        DB::transaction(function () use ($validated, $budget) {
            $budget->update([
                'ticket_id' => $validated['ticket_id'],
                'description' => $validated['description'],
                'currency' => $validated['currency'],
                'exchange_rate' => $validated['exchange_rate'],
                'user_id' => $validated['user_id'],
            ]);

            $budget->concepts()->delete();
            $budget->concepts()->createMany($validated['concepts']);
        });

        if ($request->hasFile('survey_images')) {
            foreach ($request->file('survey_images') as $image) {
                $optimizedPath = $this->imageOptimizer->optimize($image);
                $budget->addMedia($optimizedPath)
                    ->usingFileName($image->getClientOriginalName())
                    ->toMediaCollection('survey_images');
            }
        }

        if ($request->hasFile('support_files')) {
            foreach ($request->file('support_files') as $file) {
                if (str_starts_with($file->getMimeType(), 'image/')) {
                    $optimizedPath = $this->imageOptimizer->optimize($file);
                    $budget->addMedia($optimizedPath)
                        ->usingFileName($file->getClientOriginalName())
                        ->toMediaCollection('budget_files');
                } else {
                    $budget->addMedia($file)
                        ->usingFileName($file->getClientOriginalName())
                        ->toMediaCollection('budget_files');
                }
            }
        }

        return redirect()->route('budgets.show', $budget->id)->with('success', 'Presupuesto actualizado correctamente.');
    }

    /** El estatus ahora lo gestiona el ticket. Redirige al ticket relacionado. */
    public function updateStatus(Request $request, Budget $budget)
    {
        $request->validate(['status' => 'required|string']);

        if ($budget->ticket) {
            $budget->ticket->update(['status' => $request->status]);
        }

        return back()->with('success', 'Estatus actualizado en el ticket.');
    }

    public function destroy(Budget $budget)
    {
        $budget->delete();
        return back()->with('success', 'Presupuesto eliminado correctamente.');
    }

    public function storePayment(Request $request, Budget $budget)
    {
        $validated = $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'payment_date' => 'required|date',
            'payment_method' => 'required|string',
            'reference' => 'nullable|string',
            'proof' => 'nullable|file|max:5120',
        ]);

        $payment = $budget->payments()->create([
            'amount' => $validated['amount'],
            'payment_date' => $validated['payment_date'],
            'payment_method' => $validated['payment_method'],
            'reference' => $validated['reference'] ?? null,
        ]);

        if ($request->hasFile('proof')) {
            $file = $request->file('proof');
            if (str_starts_with($file->getMimeType(), 'image/')) {
                $optimizedPath = $this->imageOptimizer->optimize($file);
                $payment->addMedia($optimizedPath)
                    ->usingFileName($file->getClientOriginalName())
                    ->toMediaCollection('payment_proofs');
            } else {
                $payment->addMediaFromRequest('proof')->toMediaCollection('payment_proofs');
            }
        }

        // Check if total paid covers the latest catalog total → mark ticket as Pagado
        $budget->loadMissing(['payments', 'latestCatalog', 'ticket']);
        $totalPaid = (float) $budget->payments->sum('amount');
        $totalCost = $budget->total_cost; // Uses latest catalog total if available

        if ($totalCost > 0 && $totalPaid >= $totalCost && $budget->ticket && $budget->ticket->status !== 'Pagado') {
            $budget->ticket->update(['status' => 'Pagado']);
        }

        return back()->with('success', 'Pago registrado exitosamente.');
    }

    public function destroyPayment(BudgetPayment $payment)
    {
        $budget = $payment->budget;
        $payment->delete();

        // If total paid drops below total cost and ticket was Pagado, revert
        $budget->loadMissing(['payments', 'latestCatalog', 'ticket']);
        $totalPaid = (float) $budget->payments->sum('amount');
        $totalCost = $budget->total_cost;

        if ($totalPaid < $totalCost && $budget->ticket && $budget->ticket->status === 'Pagado') {
            $budget->ticket->update(['status' => 'Facturado']);
        }

        return back()->with('success', 'Pago eliminado.');
    }

    public function storeFile(Request $request, Budget $budget)
    {
        $request->validate([
            'files' => 'required|array',
            'files.*' => 'file|max:20480',
        ]);

        if ($request->hasFile('files')) {
            foreach ($request->file('files') as $file) {
                if (str_starts_with($file->getMimeType(), 'image/')) {
                    $optimizedPath = $this->imageOptimizer->optimize($file);
                    $budget->addMedia($optimizedPath)
                        ->usingFileName($file->getClientOriginalName())
                        ->toMediaCollection('budget_files');
                } else {
                    $budget->addMedia($file)->toMediaCollection('budget_files');
                }
            }
        }

        return back()->with('success', 'Archivos adjuntados correctamente.');
    }

    public function destroyFile($mediaId)
    {
        Media::findOrFail($mediaId)->delete();
        return back()->with('success', 'Archivo eliminado.');
    }

    public function bulkUploadFiles(Request $request)
    {
        $validated = $request->validate([
            'budget_ids' => 'required|array|min:1',
            'budget_ids.*' => 'exists:budgets,id',
            'files' => 'required|array|min:1',
            'files.*' => 'file|max:20480',
        ]);

        $budgets = Budget::whereIn('id', $validated['budget_ids'])->get();
        $count = 0;

        foreach ($budgets as $budget) {
            foreach ($request->file('files') as $file) {
                if (str_starts_with($file->getMimeType(), 'image/')) {
                    $optimizedPath = $this->imageOptimizer->optimize($file);
                    $budget->addMedia($optimizedPath)
                        ->usingFileName($file->getClientOriginalName())
                        ->toMediaCollection('budget_files');
                } else {
                    $budget->addMedia($file)
                        ->usingFileName($file->getClientOriginalName())
                        ->toMediaCollection('budget_files');
                }
                $count++;
            }
        }

        return back()->with('success', "{$count} archivos adjuntados a " . $budgets->count() . " presupuestos.");
    }

    // --- NUEVOS MÉTODOS: PAGOS A TÉCNICOS ---

    public function storeTechnicianPayment(Request $request, Budget $budget)
    {
        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
            'amount' => 'required|numeric|min:0.01',
            'payment_date' => 'required|date',
            'payment_method' => 'required|string',
            'reference' => 'nullable|string',
            'notes' => 'nullable|string',
            'proof' => 'nullable|file|max:5120',
        ]);

        $payment = $budget->technicianPayments()->create([
            'user_id' => $validated['user_id'],
            'amount' => $validated['amount'],
            'payment_date' => $validated['payment_date'],
            'payment_method' => $validated['payment_method'],
            'reference' => $validated['reference'],
            'notes' => $validated['notes'],
        ]);

        if ($request->hasFile('proof')) {
            $payment->addMediaFromRequest('proof')->toMediaCollection('tech_payment_proofs');
        }

        return back()->with('success', 'Pago a técnico registrado exitosamente.');
    }

    public function destroyTechnicianPayment(TechnicianPayment $payment)
    {
        $payment->delete();
        return back()->with('success', 'Pago a técnico eliminado.');
    }
}