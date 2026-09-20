<?php

namespace App\Http\Requests\Payroll;

use Illuminate\Foundation\Http\FormRequest;

class StorePayrollNoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('payroll.periods.manage');
    }

    public function rules(): array
    {
        return [
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'body' => ['required', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'user_id.required' => 'Selecciona al colaborador.',
            'user_id.exists' => 'El colaborador seleccionado no existe.',
            'body.required' => 'Escribe el comentario.',
            'body.max' => 'El comentario no debe exceder 1000 caracteres.',
        ];
    }
}
