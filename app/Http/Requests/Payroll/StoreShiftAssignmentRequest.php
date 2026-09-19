<?php

namespace App\Http\Requests\Payroll;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreShiftAssignmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('payroll.shifts.manage');
    }

    public function rules(): array
    {
        return [
            // Target: an individual collaborator or a whole department
            'user_id' => ['nullable', 'integer', 'exists:users,id', 'required_without:department'],
            'department' => ['nullable', 'string', 'max:255', 'required_without:user_id'],

            'type' => ['required', Rule::in(['fixed', 'rotation'])],
            'shift_id' => ['nullable', 'integer', 'exists:shifts,id', 'required_if:type,fixed'],

            'rotation' => ['nullable', 'array', 'min:2', 'required_if:type,rotation'],
            'rotation.*' => ['integer', 'exists:shifts,id'],

            'start_date' => ['required', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'is_active' => ['sometimes', 'boolean'],
            'notes' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'user_id.required_without' => 'Selecciona un colaborador o escribe un departamento.',
            'department.required_without' => 'Selecciona un colaborador o escribe un departamento.',
            'shift_id.required_if' => 'Selecciona el turno a asignar.',
            'rotation.required_if' => 'Agrega al menos dos turnos a la rotación.',
            'rotation.min' => 'La rotación debe incluir al menos dos turnos.',
            'start_date.required' => 'Indica la fecha de inicio de la asignación.',
            'end_date.after_or_equal' => 'La fecha final no puede ser anterior a la inicial.',
        ];
    }
}
