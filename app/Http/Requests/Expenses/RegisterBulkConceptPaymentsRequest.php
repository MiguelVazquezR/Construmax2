<?php

namespace App\Http\Requests\Expenses;

use App\Actions\Expenses\RegisterBulkConceptPaymentsAction;
use App\Models\Expense;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RegisterBulkConceptPaymentsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('expenses.create') || $this->user()->can('expenses.edit');
    }

    public function rules(): array
    {
        return [
            'concepts' => ['required', 'array', 'min:1'],
            'concepts.*' => ['integer', 'exists:budget_concepts,id'],
            'payment_date' => ['required', 'date'],
            'payment_method' => ['nullable', 'string', Rule::in(array_keys(Expense::paymentMethodLabels()))],
            'commission_mode' => ['required', 'string', Rule::in([
                RegisterBulkConceptPaymentsAction::COMMISSION_MODE_NONE,
                RegisterBulkConceptPaymentsAction::COMMISSION_MODE_FIXED,
                RegisterBulkConceptPaymentsAction::COMMISSION_MODE_PERCENT,
            ])],
            'commission_value' => [
                'nullable',
                'numeric',
                'min:0',
                Rule::requiredIf(fn () => $this->input('commission_mode') !== RegisterBulkConceptPaymentsAction::COMMISSION_MODE_NONE),
            ],
            'reference' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function messages(): array
    {
        return [
            'concepts.required' => 'Selecciona al menos un gasto por pagar.',
            'payment_date.required' => 'Selecciona la fecha de pago.',
            'commission_value.required' => 'Ingresa el monto o porcentaje de comisión.',
        ];
    }
}
