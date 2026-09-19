<?php

namespace App\Http\Requests\Payroll;

use App\Models\Incident;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreIncidentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('payroll.incidents.manage');
    }

    public function rules(): array
    {
        return [
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'type' => ['required', Rule::in(array_keys(Incident::TYPES))],
            'start_date' => ['required', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'is_paid' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string', 'max:255'],
            'support' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:5120'],
        ];
    }

    public function messages(): array
    {
        return [
            'user_id.required' => 'Selecciona al colaborador.',
            'user_id.exists' => 'El colaborador seleccionado no existe.',
            'type.required' => 'Selecciona el tipo de incidencia.',
            'start_date.required' => 'Indica la fecha de inicio.',
            'end_date.after_or_equal' => 'La fecha final no puede ser anterior a la inicial.',
            'support.max' => 'El comprobante no debe exceder 5 MB.',
        ];
    }
}
