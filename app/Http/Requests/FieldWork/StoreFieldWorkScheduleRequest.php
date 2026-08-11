<?php

namespace App\Http\Requests\FieldWork;

use Illuminate\Foundation\Http\FormRequest;

class StoreFieldWorkScheduleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Auth enforced by route middleware
    }

    public function rules(): array
    {
        return [
            'ticket_id'  => [
                'required',
                'integer',
                'exists:tickets,id',
            ],
            'start_time' => ['required', 'date'],
            'end_time'   => ['required', 'date', 'after:start_time'],
            'color'      => ['nullable', 'string', 'max:20'],
            'notes'      => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function messages(): array
    {
        return [
            'ticket_id.required'  => 'Por favor selecciona un ticket.',
            'ticket_id.exists'    => 'El ticket seleccionado no existe.',
            'start_time.required' => 'La fecha y hora de inicio es obligatoria.',
            'end_time.required'   => 'La fecha y hora de fin es obligatoria.',
            'end_time.after'      => 'La hora de fin debe ser posterior a la hora de inicio.',
        ];
    }
}
