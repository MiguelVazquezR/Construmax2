<?php

namespace App\Http\Requests\Payroll;

use App\Models\AttendanceLog;
use App\Models\PayrollProfile;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

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

    /**
     * A collaborator who was dismissed cannot get new punches after the
     * termination date (corrections before that date are still allowed).
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $profile = PayrollProfile::where('user_id', $this->integer('user_id'))->first();
            $punchedAt = $this->date('punched_at');

            if (! $profile?->termination_date || ! $punchedAt) {
                return;
            }

            if ($punchedAt->toDateString() > $profile->termination_date->toDateString()) {
                $validator->errors()->add(
                    'punched_at',
                    'El colaborador fue dado de baja el '.$profile->termination_date->format('d/m/Y').': no se pueden registrar marcajes posteriores.'
                );
            }
        });
    }
}
