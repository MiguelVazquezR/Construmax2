<?php

namespace App\Http\Controllers;

use App\Models\Budget;
use App\Models\BudgetPayment;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class BudgetController extends Controller
{
    public function index(Request $request)
    {
        $perPage = $request->input('perPage', 10);

        return Inertia::render('Budgets/Index', [
            'budgets' => Budget::with(['customer', 'responsible'])
                ->withSum('concepts', 'amount')
                ->filter($request->only('search', 'status'))
                ->orderBy('id', 'desc')
                ->paginate($perPage)
                ->withQueryString(),
            'filters' => $request->only(['search', 'status', 'perPage']),
        ]);
    }

    public function create()
    {
        return Inertia::render('Budgets/Create', [
            'customers' => Customer::where('is_active', true)->with('contacts')->get(),
            'users' => User::where('is_active', true)->get(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'service_type' => 'required|string',
            'status' => 'required|string',
            'user_id' => 'required|exists:users,id',
            'customer_id' => 'required|exists:customers,id',
            'customer_contact_id' => 'required|exists:customer_contacts,id',
            'branch' => 'required|string',
            'duration' => 'nullable|string',
            'priority' => 'required|string',
            'description' => 'nullable|string',
            'concepts' => 'array|min:1',
            'concepts.*.concept' => 'required|string',
            'concepts.*.amount' => 'required|numeric|min:0',
        ]);

        DB::transaction(function () use ($validated) {
            $budget = Budget::create([
                'name' => $validated['name'],
                'service_type' => $validated['service_type'],
                'status' => $validated['status'],
                'user_id' => $validated['user_id'],
                'customer_id' => $validated['customer_id'],
                'customer_contact_id' => $validated['customer_contact_id'],
                'branch' => $validated['branch'],
                'duration' => $validated['duration'],
                'priority' => $validated['priority'],
                'description' => $validated['description'],
            ]);

            $budget->concepts()->createMany($validated['concepts']);
        });

        return redirect()->route('budgets.index')->with('success', 'Presupuesto registrado correctamente.');
    }

    public function show(Budget $budget)
    {
        $budget->load(['customer', 'contact', 'responsible', 'concepts', 'payments.media', 'media']);
        $budget->append(['total_cost', 'total_paid', 'balance_due']);

        return Inertia::render('Budgets/Show', [
            'budget' => $budget,
        ]);
    }

    public function edit(Budget $budget)
    {
        $budget->load('concepts');

        return Inertia::render('Budgets/Edit', [
            'budget' => $budget,
            'customers' => Customer::where('is_active', true)->with('contacts')->get(),
            'users' => User::where('is_active', true)->get(),
        ]);
    }

    public function update(Request $request, Budget $budget)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'service_type' => 'required|string',
            'status' => 'required|string',
            'user_id' => 'required|exists:users,id',
            'customer_id' => 'required|exists:customers,id',
            'customer_contact_id' => 'required|exists:customer_contacts,id',
            'branch' => 'required|string',
            'duration' => 'nullable|string',
            'priority' => 'required|string',
            'description' => 'nullable|string',
            'concepts' => 'array|min:1',
            'concepts.*.concept' => 'required|string',
            'concepts.*.amount' => 'required|numeric|min:0',
        ]);

        DB::transaction(function () use ($validated, $budget) {
            $budget->update([
                'name' => $validated['name'],
                'service_type' => $validated['service_type'],
                'status' => $validated['status'],
                'user_id' => $validated['user_id'],
                'customer_id' => $validated['customer_id'],
                'customer_contact_id' => $validated['customer_contact_id'],
                'branch' => $validated['branch'],
                'duration' => $validated['duration'],
                'priority' => $validated['priority'],
                'description' => $validated['description'],
            ]);

            $budget->concepts()->delete();
            $budget->concepts()->createMany($validated['concepts']);
        });

        return redirect()->route('budgets.index')->with('success', 'Presupuesto actualizado correctamente.');
    }

    // --- NUEVO MÉTODO PARA KANBAN ---
    public function updateStatus(Request $request, Budget $budget)
    {
        $request->validate([
            'status' => 'required|string',
        ]);

        $budget->update(['status' => $request->status]);

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
}