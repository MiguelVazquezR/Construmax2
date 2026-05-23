<?php

namespace App\Http\Controllers;

use App\Models\Budget;
use App\Models\BudgetPayment;
use App\Models\Calendar;
use App\Models\Ticket;
use App\Models\User;
use App\Models\TechnicianPayment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class BudgetController extends Controller
{
    public function index(Request $request)
    {
        $perPage = $request->input('perPage', 10);
        
        $filters = $request->only(['search', 'status', 'perPage', 'user_id', 'branch']);

        if (!$request->has('user_id') && !$request->has('search') && !$request->has('status') && !$request->has('page') && !$request->has('branch')) {
            $filters['user_id'] = [auth()->id()];
        }

        return Inertia::render('Budgets/Index', [
            'budgets' => Budget::with(['ticket.customer', 'ticket.branch', 'responsible'])
                ->withSum('concepts', 'amount')
                ->filter($filters)
                ->orderBy('id', 'desc')
                ->paginate($perPage)
                ->withQueryString(),
            'filters' => $filters,
            'users' => User::where('is_active', true)->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function create()
    {
        $tickets = Ticket::with(['customer', 'contact', 'branch'])
            ->whereDoesntHave('budget')
            ->orderBy('id', 'desc')
            ->get();

        return Inertia::render('Budgets/Create', [
            'tickets' => $tickets,
            'users' => User::where('is_active', true)->get(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'ticket_id' => 'required|exists:tickets,id',
            'status' => 'required|string',
            'description' => 'nullable|string',
            'currency' => 'required|string|in:MXN,USD',
            'exchange_rate' => 'required|numeric|min:0.0001',
            'user_id' => 'required|exists:users,id',
            'concepts' => 'array|min:1',
            'concepts.*.concept' => 'required|string',
            'concepts.*.amount' => 'required|numeric|min:0',
        ]);

        $budget = null;

        DB::transaction(function () use ($validated, &$budget) {
            $budget = Budget::create([
                'ticket_id' => $validated['ticket_id'],
                'status' => $validated['status'],
                'description' => $validated['description'],
                'currency' => $validated['currency'],
                'exchange_rate' => $validated['exchange_rate'],
                'user_id' => $validated['user_id'],
            ]);

            $budget->concepts()->createMany($validated['concepts']);
        });

        if ($request->boolean('quick_create')) {
            return response()->json([
                'budget' => $budget?->load('ticket.customer'),
                'message' => 'Presupuesto creado exitosamente.'
            ], 201);
        }

        return redirect()->route('budgets.index')->with('success', 'Presupuesto registrado correctamente.');
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
        ]);

        $budget->append(['total_cost', 'total_paid', 'balance_due']);

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

        return Inertia::render('Budgets/Edit', [
            'budget' => $budget,
            'tickets' => $tickets,
            'users' => User::where('is_active', true)->get(),
        ]);
    }

    public function update(Request $request, Budget $budget)
    {
        $validated = $request->validate([
            'ticket_id' => 'required|exists:tickets,id',
            'status' => 'required|string',
            'description' => 'nullable|string',
            'currency' => 'required|string|in:MXN,USD',
            'exchange_rate' => 'required|numeric|min:0.0001',
            'user_id' => 'required|exists:users,id',
            'concepts' => 'array|min:1',
            'concepts.*.concept' => 'required|string',
            'concepts.*.amount' => 'required|numeric|min:0',
        ]);

        DB::transaction(function () use ($validated, $budget) {
            $budget->update([
                'ticket_id' => $validated['ticket_id'],
                'status' => $validated['status'],
                'description' => $validated['description'],
                'currency' => $validated['currency'],
                'exchange_rate' => $validated['exchange_rate'],
                'user_id' => $validated['user_id'],
            ]);

            $budget->concepts()->delete();
            $budget->concepts()->createMany($validated['concepts']);
        });

        return redirect()->route('budgets.index')->with('success', 'Presupuesto actualizado correctamente.');
    }

    public function updateStatus(Request $request, Budget $budget)
    {
        $request->validate([
            'status' => 'required|string',
        ]);

        $oldStatus = $budget->status;
        $newStatus = $request->status;

        $budget->update(['status' => $newStatus]);

        // --- AUTOMATIZACIÓN CALENDARIO (COBRANZA) ---
        // Si el estado cambia a 'Facturado', verificamos si el cliente tiene días de crédito
        if ($newStatus === 'Facturado' && $oldStatus !== 'Facturado') {
            $budget->load('ticket.customer');
            $paymentDays = $budget->ticket->customer->payment_days;

            if ($paymentDays && $paymentDays > 0) {
                // Calculamos fecha de recordatorio (Hoy + días de crédito)
                // Fijamos la hora a las 9:00 AM para que sea visible al iniciar el día
                $reminderDate = now()->addDays($paymentDays)->setTime(9, 0, 0);
                
                Calendar::create([
                    'user_id' => $budget->user_id, // Asignamos el recordatorio al responsable del presupuesto
                    'type' => 'Recordatorio',
                    'title' => "Cobranza: {$budget->ticket->name}",
                    'description' => "Vencimiento de plazo de pago ({$paymentDays} días) para el cliente {$budget->ticket->customer->name}.\nPresupuesto #{$budget->id}.",
                    'start_time' => $reminderDate,
                    'end_time' => $reminderDate->copy()->addHour(), // Duración de 1 hora por defecto
                    'is_completed' => false,
                ]);
            }
        }

        return back()->with('success', 'Estatus actualizado.');
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
            $payment->addMediaFromRequest('proof')->toMediaCollection('payment_proofs');
        }

        return back()->with('success', 'Pago registrado exitosamente.');
    }

    public function destroyPayment(BudgetPayment $payment)
    {
        $payment->delete();
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
                $budget->addMedia($file)->toMediaCollection('budget_files');
            }
        }

        return back()->with('success', 'Archivos adjuntados correctamente.');
    }

    public function destroyFile($mediaId)
    {
        Media::findOrFail($mediaId)->delete();
        return back()->with('success', 'Archivo eliminado.');
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
            'proof' => 'required|file|max:5120', // Comprobante obligatorio como solicitaste
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