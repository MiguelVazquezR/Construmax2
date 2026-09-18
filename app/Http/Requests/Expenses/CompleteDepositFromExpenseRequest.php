<?php

namespace App\Http\Requests\Expenses;

use App\Models\Deposit;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class CompleteDepositFromExpenseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('expenses.edit') || $this->user()->can('deposits.approve');
    }

    public function rules(): array
    {
        return [
            'commission_amount' => ['nullable', 'numeric', 'min:0'],
            'voucher' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:10240'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            /** @var Deposit|null $deposit */
            $deposit = $this->route('expense')?->deposit;

            $hasVoucher = $deposit && $deposit->getFirstMedia('voucher');

            // The voucher is what marks the deposit as realized, so it is
            // required unless one was already uploaded in the deposits module.
            if (!$hasVoucher && !$this->hasFile('voucher')) {
                $validator->errors()->add('voucher', 'Adjunta el comprobante del depósito.');
            }
        });
    }

    public function messages(): array
    {
        return [
            'voucher.mimes' => 'El comprobante debe ser un archivo JPG, PNG o PDF.',
            'voucher.max' => 'El comprobante no debe exceder 10 MB.',
            'commission_amount.min' => 'La comisión no puede ser negativa.',
        ];
    }
}
