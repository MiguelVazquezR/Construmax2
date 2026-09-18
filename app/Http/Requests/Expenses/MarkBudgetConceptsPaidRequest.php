<?php

namespace App\Http\Requests\Expenses;

use App\Models\Expense;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MarkBudgetConceptsPaidRequest extends FormRequest
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
            'commission_amount' => ['nullable', 'numeric', 'min:0'],
            'reference' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function messages(): array
    {
        return [
            'concepts.required' => 'Selecciona al menos un concepto del desglose.',
            'payment_date.required' => 'Selecciona la fecha de pago.',
        ];
    }
}
