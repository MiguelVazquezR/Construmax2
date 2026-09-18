<?php

namespace App\Http\Requests\Expenses;

use App\Models\Expense;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RegisterBudgetConceptPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('expenses.create') || $this->user()->can('expenses.edit');
    }

    public function rules(): array
    {
        return [
            'status' => ['required', 'string', Rule::in([Expense::STATUS_PENDING, Expense::STATUS_PAID, Expense::STATUS_CANCELLED])],
            'payment_date' => ['required', 'date'],
            'payment_method' => ['nullable', 'string', Rule::in(array_keys(Expense::paymentMethodLabels()))],
            'commission_amount' => ['nullable', 'numeric', 'min:0'],
            'reference' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'receipts' => ['nullable', 'array', 'max:5'],
            'receipts.*' => ['file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:10240'],
            'remove_receipt_ids' => ['nullable', 'array'],
            'remove_receipt_ids.*' => ['integer'],
        ];
    }

    public function messages(): array
    {
        return [
            'payment_date.required' => 'Selecciona la fecha de pago.',
            'status.required' => 'Selecciona el estatus del gasto.',
            'receipts.max' => 'Puedes adjuntar hasta 5 comprobantes por gasto.',
            'receipts.*.mimes' => 'Los comprobantes deben ser archivos JPG, PNG, WEBP o PDF.',
            'receipts.*.max' => 'Cada comprobante no debe exceder 10 MB.',
        ];
    }
}
