<?php

namespace App\Http\Controllers;

use App\Actions\Invoices\UploadInvoiceAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Invoices\UploadInvoiceRequest;
use App\Models\Budget;
use App\Services\Invoices\InvoiceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class InvoiceController extends Controller
{
    public function __construct(
        private readonly InvoiceService $invoiceService,
        private readonly UploadInvoiceAction $uploadInvoiceAction
    ) {}

    public function index(Request $request): Response
    {
        if (!$request->user()->can('invoices.index')) {
            abort(403);
        }

        $invoices = $this->invoiceService->getPendingInvoices($request->all());

        return Inertia::render('Invoices/Index', [
            'invoices' => $invoices,
            'filters'  => $request->only(['search', 'status']),
        ]);
    }

    public function upload(UploadInvoiceRequest $request, Budget $budget): RedirectResponse
    {
        $this->uploadInvoiceAction->execute($budget, $request->validated());

        return redirect()->back();
    }
}