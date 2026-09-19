<?php

namespace App\Http\Requests\Payroll;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreShiftRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('payroll.shifts.manage');
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'type' => ['required', Rule::in(['fixed', 'flexible'])],

            'start_time' => ['nullable', 'required_if:type,fixed', 'date_format:H:i,H:i:s'],
            'end_time' => ['nullable', 'required_if:type,fixed', 'date_format:H:i,H:i:s'],
            'meal_minutes' => ['required', 'integer', 'min:0', 'max:480'],
            'is_meal_paid' => ['sometimes', 'boolean'],

            'days' => ['required', 'array', 'min:1'],
            'days.*' => ['integer', 'between:1,7'],

            'required_daily_hours' => ['nullable', 'required_if:type,flexible', 'numeric', 'min:0.5', 'max:24'],
            'late_tolerance_minutes' => ['nullable', 'integer', 'min:0', 'max:120'],
            'is_active' => ['sometimes', 'boolean'],
            'description' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'El nombre del turno es obligatorio.',
            'start_time.required_if' => 'Indica la hora de entrada del turno fijo.',
            'end_time.required_if' => 'Indica la hora de salida del turno fijo.',
            'days.required' => 'Selecciona al menos un día de la semana.',
            'required_daily_hours.required_if' => 'Indica las horas diarias requeridas del turno flexible.',
        ];
    }
}
