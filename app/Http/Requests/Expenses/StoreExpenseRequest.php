<?php

namespace App\Http\Requests\Expenses;

use App\Models\Expense;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreExpenseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('expenses.create');
    }

    public function rules(): array
    {
        return [
            // General expenses require a category; budget expenses do not.
            'expense_category_id' => [
                Rule::requiredIf(fn () => !$this->filled('budget_id')),
                'nullable',
                'integer',
                'exists:expense_categories,id',
            ],
            'budget_id' => ['nullable', 'integer', 'exists:budgets,id'],
            'is_commission' => ['nullable', 'boolean'],
            'concept' => ['required', 'string', 'max:255'],
            'reference' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'commission_amount' => ['nullable', 'numeric', 'min:0'],
            'expense_date' => ['required', 'date'],
            'payment_method' => ['nullable', 'string', Rule::in(array_keys(Expense::paymentMethodLabels()))],
            'status' => ['required', 'string', Rule::in([Expense::STATUS_PENDING, Expense::STATUS_PAID, Expense::STATUS_CANCELLED])],
            'receipts' => ['nullable', 'array', 'max:5'],
            'receipts.*' => ['file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:10240'],
        ];
    }

    public function messages(): array
    {
        return [
            'expense_category_id.required' => 'Selecciona una categoría para el gasto.',
            'expense_category_id.exists' => 'La categoría seleccionada no existe.',
            'budget_id.exists' => 'El presupuesto seleccionado no existe.',
            'concept.required' => 'Escribe el concepto del gasto.',
            'amount.required' => 'Captura el monto del gasto.',
            'amount.min' => 'El monto debe ser mayor a cero.',
            'commission_amount.min' => 'La comisión no puede ser negativa.',
            'expense_date.required' => 'Selecciona la fecha del gasto.',
            'status.required' => 'Selecciona el estatus del gasto.',
            'receipts.max' => 'Puedes adjuntar hasta 5 comprobantes por gasto.',
            'receipts.*.mimes' => 'Los comprobantes deben ser archivos JPG, PNG, WEBP o PDF.',
            'receipts.*.max' => 'Cada comprobante no debe exceder 10 MB.',
        ];
    }
}
