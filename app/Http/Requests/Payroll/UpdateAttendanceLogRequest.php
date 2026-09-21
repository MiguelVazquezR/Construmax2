<?php

namespace App\Http\Requests\Payroll;

use App\Models\AttendanceLog;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAttendanceLogRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('payroll.periods.manage');
    }

    public function rules(): array
    {
        return [
            'punched_at' => ['required', 'date'],
            'type' => ['sometimes', Rule::in(array_keys(AttendanceLog::TYPES))],
            // Optional: when provided it stays in the audit trail of the record.
            'edit_reason' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'punched_at.required' => 'Indica la hora correcta del registro.',
            'edit_reason.max' => 'El motivo no puede exceder los 255 caracteres.',
        ];
    }
}
