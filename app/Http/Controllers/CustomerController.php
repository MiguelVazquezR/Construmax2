<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class CustomerController extends Controller
{
    public function index(Request $request)
    {
        $perPage = $request->input('perPage', 10);

        return Inertia::render('Customers/Index', [
            'customers' => Customer::filter($request->only('search'))
                ->orderBy('id', 'desc')
                ->paginate($perPage)
                ->withQueryString(),
            'filters' => $request->only(['search', 'perPage']),
        ]);
    }

    public function create()
    {
        return Inertia::render('Customers/Create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'business_name' => 'required|string|max:255',
            'rfc' => 'required|string|max:20|unique:customers,rfc',
            'payment_condition' => 'required|string',
            'payment_method' => 'required|string',
            'invoice_usage' => 'required|string',
            'currency' => 'required|string|in:MXN,USD',
            'payment_days' => 'nullable|integer|min:0|max:365', 
            'contacts' => 'array|min:1',
            'contacts.*.name' => 'required|string|max:255',
            'contacts.*.email' => 'required|email|max:255',
            'contacts.*.phone' => 'required|string|max:20',
            'contacts.*.position' => 'required|string|max:100',
            'contacts.*.branches' => 'required|string|max:255',
        ]);

        DB::transaction(function () use ($validated) {
            $customer = Customer::create([
                'name' => $validated['name'],
                'business_name' => $validated['business_name'],
                'rfc' => $validated['rfc'],
                'payment_condition' => $validated['payment_condition'],
                'payment_method' => $validated['payment_method'],
                'invoice_usage' => $validated['invoice_usage'],
                'currency' => $validated['currency'],
                'payment_days' => $validated['payment_days'] ?? 0,
                'is_active' => true,
            ]);

            $customer->contacts()->createMany($validated['contacts']);
        });

        return redirect()->route('customers.index')->with('success', 'Cliente registrado exitosamente.');
    }

    public function show(Customer $customer)
    {
        // Cargamos contactos y presupuestos (ordenados por fecha e incluyendo responsable y suma de costos)
        $customer->load([
            'contacts', 
            'budgets' => function ($query) {
                $query->orderBy('id', 'desc')
                      ->with('responsible:id,name,profile_photo_path') // Optimizamos carga de usuario
                      ->withSum('concepts', 'amount'); // Calculamos total sin cargar todos los conceptos
            }
        ]);

        return Inertia::render('Customers/Show', [
            'customer' => $customer,
        ]);
    }

    public function edit(Customer $customer)
    {
        return Inertia::render('Customers/Edit', [
            'customer' => $customer->load('contacts'),
        ]);
    }

    public function update(Request $request, Customer $customer)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'business_name' => 'required|string|max:255',
            'rfc' => 'required|string|max:20|unique:customers,rfc,' . $customer->id,
            'payment_condition' => 'required|string',
            'payment_method' => 'required|string',
            'invoice_usage' => 'required|string',
            'currency' => 'required|string|in:MXN,USD',
            'payment_days' => 'nullable|integer|min:0|max:365',
            'contacts' => 'array|min:1',
            'contacts.*.name' => 'required|string|max:255',
            'contacts.*.email' => 'required|email|max:255',
            'contacts.*.phone' => 'required|string|max:20',
            'contacts.*.position' => 'required|string|max:100',
            'contacts.*.branches' => 'required|string|max:255',
        ]);

        DB::transaction(function () use ($validated, $customer) {
            $customer->update([
                'name' => $validated['name'],
                'business_name' => $validated['business_name'],
                'rfc' => $validated['rfc'],
                'payment_condition' => $validated['payment_condition'],
                'payment_method' => $validated['payment_method'],
                'invoice_usage' => $validated['invoice_usage'],
                'currency' => $validated['currency'],
                'payment_days' => $validated['payment_days'] ?? 0,
            ]);

            $customer->contacts()->delete();
            $customer->contacts()->createMany($validated['contacts']);
        });

        return redirect()->route('customers.index')->with('success', 'Cliente actualizado exitosamente.');
    }
    
    public function toggleStatus(Customer $customer)
    {
        $customer->update(['is_active' => !$customer->is_active]);
        return back()->with('success', 'Estatus del cliente actualizado.');
    }

    public function destroy(Customer $customer)
    {
        $customer->delete();
        return back()->with('success', 'Cliente eliminado correctamente.');
    }
}