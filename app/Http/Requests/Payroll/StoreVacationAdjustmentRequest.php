<?php

namespace App\Http\Requests\Payroll;

use App\Models\VacationAdjustment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreVacationAdjustmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('payroll.vacations.manage');
    }

    public function rules(): array
    {
        return [
            'type' => ['required', Rule::in(array_keys(VacationAdjustment::TYPES))],
            'days' => ['required', 'numeric', 'min:-365', 'max:365'],
            'reason' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $days = round((float) $this->input('days'), 2);

            if ($days === 0.0) {
                $validator->errors()->add('days', 'Los días del movimiento deben ser distintos de cero.');
            }

            if ($days < 0 && ! in_array($this->input('type'), [VacationAdjustment::TYPE_ADJUSTMENT, VacationAdjustment::TYPE_TAKEN], true)) {
                $validator->errors()->add('days', 'Los días negativos solo se permiten en el ajuste manual o al registrar días tomados.');
            }
        });
    }

    public function messages(): array
    {
        return [
            'type.required' => 'Selecciona el tipo de movimiento.',
            'type.in' => 'El tipo de movimiento no es válido.',
            'days.required' => 'Indica la cantidad de días.',
            'days.numeric' => 'Los días deben ser un número.',
            'days.min' => 'No se pueden capturar más de 365 días negativos en un movimiento.',
            'days.max' => 'No se pueden capturar más de 365 días en un movimiento.',
        ];
    }
}
