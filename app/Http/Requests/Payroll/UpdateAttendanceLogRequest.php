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
            // Every manual correction must be justified (audited).
            'edit_reason' => ['required', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'punched_at.required' => 'Indica la hora correcta del marcaje.',
            'edit_reason.required' => 'Describe el motivo del cambio (quedará en la auditoría).',
        ];
    }
}
