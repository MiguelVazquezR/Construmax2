<?php

namespace App\Http\Requests\Payroll;

use App\Models\PayrollAdjustment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePayrollAdjustmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('payroll.periods.manage');
    }

    public function rules(): array
    {
        return [
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'type' => ['required', Rule::in(array_keys(PayrollAdjustment::TYPES))],
            'concept' => ['required', 'string', 'max:120'],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:9999999'],
            'notes' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'user_id.required' => 'Selecciona al colaborador.',
            'type.required' => 'Selecciona si es una percepción o una deducción.',
            'concept.required' => 'Indica el concepto del ajuste.',
            'amount.required' => 'Captura el monto del ajuste.',
            'amount.min' => 'El monto debe ser mayor a cero.',
        ];
    }
}
