<?php

namespace App\Http\Requests\Payroll;

use Illuminate\Foundation\Http\FormRequest;

class StoreVacationRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user->can('payroll.vacations.manage')
            || (bool) $user->payrollProfile?->is_attendance_subject;
    }

    public function rules(): array
    {
        return [
            // Only honored for users with the payroll.vacations.manage permission.
            'user_id' => ['sometimes', 'integer', 'exists:users,id'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'reason' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'start_date.required' => 'Indica la fecha de inicio de las vacaciones.',
            'end_date.required' => 'Indica la fecha final de las vacaciones.',
            'end_date.after_or_equal' => 'La fecha final no puede ser anterior a la inicial.',
        ];
    }
}
