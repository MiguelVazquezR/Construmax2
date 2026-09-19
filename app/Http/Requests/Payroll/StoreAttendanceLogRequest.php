<?php

namespace App\Http\Requests\Payroll;

use App\Models\AttendanceLog;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAttendanceLogRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('payroll.periods.manage');
    }

    public function rules(): array
    {
        return [
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'type' => ['required', Rule::in(array_keys(AttendanceLog::TYPES))],
            'punched_at' => ['required', 'date'],
            'edit_reason' => ['required', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'user_id.required' => 'Selecciona al colaborador.',
            'type.required' => 'Selecciona el tipo de marcaje.',
            'punched_at.required' => 'Indica la fecha y hora del marcaje.',
            'edit_reason.required' => 'Describe el motivo del registro manual.',
        ];
    }
}
