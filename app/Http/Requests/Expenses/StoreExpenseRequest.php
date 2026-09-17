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
            'expense_category_id' => ['required', 'integer', 'exists:expense_categories,id'],
            'ticket_id' => ['nullable', 'integer', 'exists:tickets,id'],
            'concept' => ['required', 'string', 'max:255'],
            'reference' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'expense_date' => ['required', 'date'],
            'payment_method' => ['nullable', 'string', Rule::in(array_keys(Expense::paymentMethodLabels()))],
            'status' => ['required', 'string', Rule::in([Expense::STATUS_PENDING, Expense::STATUS_PAID, Expense::STATUS_CANCELLED])],
            'receipt' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:10240'],
        ];
    }

    public function messages(): array
    {
        return [
            'expense_category_id.required' => 'Selecciona una categoría para el gasto.',
            'expense_category_id.exists' => 'La categoría seleccionada no existe.',
            'ticket_id.exists' => 'El ticket seleccionado no existe.',
            'concept.required' => 'Escribe el concepto del gasto.',
            'amount.required' => 'Captura el monto del gasto.',
            'amount.min' => 'El monto debe ser mayor a cero.',
            'expense_date.required' => 'Selecciona la fecha del gasto.',
            'status.required' => 'Selecciona el estatus del gasto.',
            'receipt.mimes' => 'El comprobante debe ser un archivo JPG, PNG, WEBP o PDF.',
            'receipt.max' => 'El comprobante no debe exceder 10 MB.',
        ];
    }
}
