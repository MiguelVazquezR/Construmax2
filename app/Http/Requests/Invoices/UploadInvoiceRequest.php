<?php

namespace App\Http\Requests\Invoices;

use Illuminate\Foundation\Http\FormRequest;

class UploadInvoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('invoices.upload');
    }

    public function rules(): array
    {
        return [
            'invoice_date'   => ['required', 'date'],
            'invoice_number' => ['required', 'string', 'max:255'],
            'file'           => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
        ];
    }
}