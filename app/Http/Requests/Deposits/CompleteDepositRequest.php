<?php

namespace App\Http\Requests\Deposits;

use Illuminate\Foundation\Http\FormRequest;

class CompleteDepositRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Public route — no auth required
    }

    public function rules(): array
    {
        return [
            'commission_amount' => ['nullable', 'numeric', 'min:0'],
            'voucher'           => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:10240'],
        ];
    }

    public function messages(): array
    {
        return [
            'commission_amount.numeric' => 'La comisión debe ser un número válido.',
            'commission_amount.min'     => 'La comisión no puede ser negativa.',
            'voucher.mimes'             => 'El comprobante debe ser un archivo JPG, PNG o PDF.',
            'voucher.max'               => 'El comprobante no debe exceder 10 MB.',
        ];
    }
}
