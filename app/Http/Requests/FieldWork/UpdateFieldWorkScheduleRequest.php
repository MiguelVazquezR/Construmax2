<?php

namespace App\Http\Requests\FieldWork;

use Illuminate\Foundation\Http\FormRequest;

class UpdateFieldWorkScheduleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Auth enforced by route middleware
    }

    public function rules(): array
    {
        return [
            'ticket_id'  => [
                'sometimes',
                'integer',
                'exists:tickets,id',
            ],
            'start_time' => ['sometimes', 'date'],
            'end_time'   => ['sometimes', 'date', 'after:start_time'],
            'color'      => ['nullable', 'string', 'max:20'],
            'notes'      => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function messages(): array
    {
        return [
            'end_time.after' => 'La hora de fin debe ser posterior a la hora de inicio.',
        ];
    }
}
