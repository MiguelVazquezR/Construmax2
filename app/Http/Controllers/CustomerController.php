<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\CustomerBranch;
use App\Models\Ticket;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class CustomerController extends Controller
{
    public function index(Request $request)
    {
        $perPage = $request->input('perPage', 10);

        return Inertia::render('Customers/Index', [
            'customers' => Customer::with(['contacts.branches', 'branches', 'media'])
                ->filter($request->only(['search', 'region', 'contact'])) 
                ->orderBy('id', 'desc')
                ->paginate($perPage)
                ->withQueryString(),
            'filters' => $request->only(['search', 'perPage', 'region', 'contact']), 
        ]);
    }

    public function create()
    {
        return Inertia::render('Customers/Create');
    }

    public function store(Request $request)
    {
        $rules = [
            'type' => 'required|string|in:customer,prospect',
            'name' => 'required|string|max:255',
            'business_name' => 'required|string|max:255',
            'contacts' => 'required|array|min:1',
            'contacts.*.name' => 'required|string|max:255',
            'contacts.*.email' => 'required|email|max:255',
            'contacts.*.phone' => 'nullable|string|max:20',
            'contacts.*.position' => 'required|string|max:100',
            'contacts.*.branch_indices' => 'nullable|array',
            'contacts.*.branch_indices.*' => 'integer',
            'logo' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:2048',
            'files' => 'nullable|array',
            'files.*' => 'nullable|file|mimes:pdf,doc,docx,xls,xlsx,jpeg,png,jpg,webp|max:10240',
        ];

        if ($request->input('type') === 'customer') {
            $rules['rfc'] = 'required|string|max:20';
            $rules['payment_condition'] = 'required|string';
            $rules['payment_method'] = 'required|string';
            $rules['invoice_usage'] = 'required|string';
            $rules['currency'] = 'required|string|in:MXN,USD';
            $rules['payment_days'] = 'nullable|integer|min:0|max:365';
            $rules['branches'] = 'required|array|min:1';
            $rules['branches.*.country'] = 'required|string|max:100';
            $rules['branches.*.region'] = 'required|string|max:100';
            $rules['branches.*.city'] = 'required|string|max:100';
            $rules['branches.*.unit'] = 'required|string|max:255';
            $rules['branches.*.branch_name'] = 'required|string|max:255';
            $rules['contacts.*.branch_indices'] = 'required|array|min:1';
            $rules['contacts.*.branch_indices.*'] = 'integer';
        } else {
            $rules['rfc'] = 'nullable|string|max:20';
        }

        $validated = $request->validate($rules);

        DB::transaction(function () use ($validated, $request) {
            $customer = Customer::create([
                'type' => $validated['type'],
                'name' => $validated['name'],
                'business_name' => $validated['business_name'] ?? null,
                'rfc' => $validated['rfc'] ?? null,
                'payment_condition' => $validated['payment_condition'] ?? '',
                'payment_method' => $validated['payment_method'] ?? 'Transferencia',
                'invoice_usage' => $validated['invoice_usage'] ?? '',
                'currency' => $validated['currency'] ?? 'MXN',
                'payment_days' => $validated['payment_days'] ?? 0,
                'is_active' => true,
            ]);

            if ($validated['type'] === 'customer' && !empty($validated['branches'] ?? [])) {
                $createdBranches = [];
                foreach ($validated['branches'] as $index => $branchData) {
                    $createdBranches[$index] = $customer->branches()->create($branchData);
                }

                foreach ($validated['contacts'] as $contactData) {
                    $branchIndices = $contactData['branch_indices'] ?? [];
                    unset($contactData['branch_indices']);

                    $contact = $customer->contacts()->create($contactData);

                    $branchIds = collect($branchIndices)->map(function ($idx) use ($createdBranches) {
                        return $createdBranches[$idx]->id ?? null;
                    })->filter()->toArray();

                    $contact->branches()->attach($branchIds);
                }
            } else {
                // Prospect: create contacts without branches
                foreach ($validated['contacts'] as $contactData) {
                    unset($contactData['branch_indices']);
                    $customer->contacts()->create($contactData);
                }
            }

            if ($request->hasFile('logo')) {
                $customer->addMediaFromRequest('logo')
                    ->toMediaCollection('logo');
            }

            if ($request->hasFile('files')) {
                foreach ($request->file('files') as $file) {
                    $customer->addMedia($file)
                        ->usingFileName($file->getClientOriginalName())
                        ->toMediaCollection('customer_files');
                }
            }
        });

        return redirect()->route('customers.index')->with('success', 'Cliente registrado exitosamente.');
    }

    public function show(Customer $customer)
    {
        $customer->load([
            'branches',
            'contacts.branches', 
            'media',
            'tickets' => function ($query) {
                $query->orderBy('id', 'desc')
                      ->with(['seller:id,name,profile_photo_path', 'branch']);
            }
        ]);

        return Inertia::render('Customers/Show', [
            'customer' => $customer,
        ]);
    }

    public function edit(Customer $customer)
    {
        return Inertia::render('Customers/Edit', [
            'customer' => $customer->load(['branches', 'contacts.branches', 'media']),
        ]);
    }

    public function update(Request $request, Customer $customer)
    {
        $rules = [
            'type' => 'required|string|in:customer,prospect',
            'name' => 'required|string|max:255',
            'business_name' => 'nullable|string|max:255',
            'rfc' => 'nullable|string|max:20',
            'contacts' => 'required|array|min:1',
            'contacts.*.id' => 'nullable|integer|exists:customer_contacts,id',
            'contacts.*.name' => 'required|string|max:255',
            'contacts.*.email' => 'required|email|max:255',
            'contacts.*.phone' => 'nullable|string|max:20',
            'contacts.*.position' => 'required|string|max:100',
            'contacts.*.branch_indices' => 'nullable|array',
            'contacts.*.branch_indices.*' => 'integer',
        ];

        if ($request->input('type') === 'customer') {
            $rules['rfc'] = 'required|string|max:20';
            $rules['payment_condition'] = 'required|string';
            $rules['payment_method'] = 'required|string';
            $rules['invoice_usage'] = 'required|string';
            $rules['currency'] = 'required|string|in:MXN,USD';
            $rules['payment_days'] = 'nullable|integer|min:0|max:365';
            $rules['branches'] = 'required|array|min:1';
            $rules['branches.*.id'] = 'nullable|integer|exists:customer_branches,id';
            $rules['branches.*.country'] = 'required|string|max:100';
            $rules['branches.*.region'] = 'required|string|max:100';
            $rules['branches.*.city'] = 'required|string|max:100';
            $rules['branches.*.unit'] = 'required|string|max:255';
            $rules['branches.*.branch_name'] = 'required|string|max:255';
            $rules['contacts.*.branch_indices'] = 'required|array|min:1';
            $rules['contacts.*.branch_indices.*'] = 'integer';
        }

        $validated = $request->validate($rules);

        DB::transaction(function () use ($validated, $customer) {
            $customer->update([
                'type' => $validated['type'],
                'name' => $validated['name'],
                'business_name' => $validated['business_name'] ?? null,
                'rfc' => $validated['rfc'] ?? null,
                'payment_condition' => $validated['payment_condition'] ?? $customer->payment_condition,
                'payment_method' => $validated['payment_method'] ?? $customer->payment_method,
                'invoice_usage' => $validated['invoice_usage'] ?? $customer->invoice_usage,
                'currency' => $validated['currency'] ?? $customer->currency,
                'payment_days' => $validated['payment_days'] ?? $customer->payment_days,
            ]);

            // ── Sync branches (upsert instead of delete + recreate) - Only for customers ──
            if ($validated['type'] === 'customer' && !empty($validated['branches'] ?? [])) {
                $existingBranchIds = $customer->branches()->pluck('id')->toArray();
                $incomingBranchIds = [];
                $createdBranches = [];

                foreach ($validated['branches'] as $index => $branchData) {
                    $branchId = $branchData['id'] ?? null;
                    unset($branchData['id']);

                    if ($branchId && in_array($branchId, $existingBranchIds)) {
                        $branch = $customer->branches()->find($branchId);
                        $branch->update($branchData);
                    } else {
                        $branch = $customer->branches()->create($branchData);
                    }

                    $createdBranches[$index] = $branch;
                    $incomingBranchIds[] = $branch->id;
                }

                // Delete branches removed by the user
                $branchesToDelete = array_diff($existingBranchIds, $incomingBranchIds);
                if (!empty($branchesToDelete)) {
                    Ticket::whereIn('customer_branch_id', $branchesToDelete)
                        ->update(['customer_branch_id' => null]);
                    $customer->branches()->whereIn('id', $branchesToDelete)->delete();
                }
            }

            // ── Sync contacts (upsert instead of delete + recreate) ──
            $existingContactIds = $customer->contacts()->pluck('id')->toArray();
            $incomingContactIds = [];

            foreach ($validated['contacts'] as $contactData) {
                $contactId = $contactData['id'] ?? null;
                $branchIndices = $contactData['branch_indices'] ?? [];
                unset($contactData['id'], $contactData['branch_indices']);

                if ($contactId && in_array($contactId, $existingContactIds)) {
                    $contact = $customer->contacts()->find($contactId);
                    $contact->update($contactData);
                } else {
                    $contact = $customer->contacts()->create($contactData);
                }

                $incomingContactIds[] = $contact->id;

                if (!empty($createdBranches)) {
                    $branchIds = collect($branchIndices)->map(function ($idx) use ($createdBranches) {
                        return $createdBranches[$idx]->id ?? null;
                    })->filter()->toArray();

                    $contact->branches()->sync($branchIds);
                }
            }

            // Delete contacts removed by the user
            $contactsToDelete = array_diff($existingContactIds, $incomingContactIds);
            if (!empty($contactsToDelete)) {
                Ticket::whereIn('customer_contact_id', $contactsToDelete)
                    ->update(['customer_contact_id' => null]);
                $customer->contacts()->whereIn('id', $contactsToDelete)->delete();
            }
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

    public function convertToCustomer(Customer $customer)
    {
        if ($customer->type !== 'prospect') {
            return back()->with('error', 'Solo los prospectos pueden convertirse a cliente.');
        }

        $customer->update(['type' => 'customer']);
        return back()->with('success', 'Prospecto convertido a cliente correctamente.');
    }

    public function deleteMedia(Customer $customer, $mediaId)
    {
        $media = $customer->media()->findOrFail($mediaId);
        $media->delete();

        return back()->with('success', 'Archivo eliminado correctamente.');
    }

    public function deleteLogo(Customer $customer)
    {
        $customer->clearMediaCollection('logo');
        return back()->with('success', 'Logo eliminado correctamente.');
    }

    public function uploadFiles(Request $request, Customer $customer)
    {
        $request->validate([
            'logo' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:2048',
            'files' => 'nullable|array',
            'files.*' => 'nullable|file|mimes:pdf,doc,docx,xls,xlsx,jpeg,png,jpg,webp|max:10240',
        ]);

        if ($request->hasFile('logo')) {
            $customer->clearMediaCollection('logo');
            $customer->addMediaFromRequest('logo')
                ->toMediaCollection('logo');
        }

        if ($request->hasFile('files')) {
            foreach ($request->file('files') as $file) {
                $customer->addMedia($file)
                    ->usingFileName($file->getClientOriginalName())
                    ->toMediaCollection('customer_files');
            }
        }

        return back()->with('success', 'Archivos subidos correctamente.');
    }

    public function quickStoreBranch(Request $request)
    {
        $validated = $request->validate([
            'customer_id' => 'required|exists:customers,id',
            'country' => 'required|string|max:100',
            'region' => 'required|string|max:100',
            'city' => 'required|string|max:100',
            'unit' => 'required|string|max:255',
            'branch_name' => 'required|string|max:255',
        ]);

        $branch = CustomerBranch::create($validated);

        return response()->json([
            'branch' => $branch->load('customer'),
            'message' => 'Sucursal registrada correctamente.',
        ]);
    }
}