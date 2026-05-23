<?php

namespace App\Actions\Invoices;

use App\Models\Budget;

class UploadInvoiceAction
{
    public function execute(Budget $budget, array $data): void
    {
        $budget->update([
            'invoice_date'   => $data['invoice_date'],
            'invoice_number' => $data['invoice_number'],
            'status'         => 'Facturado',
        ]);

        if (isset($data['file'])) {
            $budget->addMedia($data['file'])->toMediaCollection('invoice_document');
        }
    }
}