<?php

namespace App\Http\Requests\Payroll;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAttendanceDayOverrideRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('payroll.periods.manage');
    }

    public function rules(): array
    {
        return [
            'date' => ['required', 'date'],
            'late_ignored' => ['sometimes', 'boolean'],
            'notes' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'date.required' => 'Indica el día a actualizar.',
        ];
    }
}
