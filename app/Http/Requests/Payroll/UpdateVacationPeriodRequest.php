<?php

namespace App\Http\Requests\Payroll;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateVacationPeriodRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('payroll.vacations.manage');
    }

    public function rules(): array
    {
        $period = $this->route('period');

        return [
            'year_number' => [
                'required', 'integer', 'min:1', 'max:80',
                Rule::unique('vacation_periods', 'year_number')
                    ->where(fn ($query) => $query->where('user_id', $period->user_id)->whereNull('deleted_at'))
                    ->ignore($period->id),
            ],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after:start_date'],
            'entitled_days' => ['required', 'numeric', 'min:0', 'max:365'],
            'accrued_days' => ['required', 'numeric', 'min:0', 'max:365'],
            'taken_days' => ['required', 'numeric', 'min:0', 'max:365'],
            'premium_paid' => ['boolean'],
            'premium_paid_at' => ['nullable', 'date'],
        ];
    }

    public function messages(): array
    {
        return [
            'year_number.unique' => 'Ya existe un periodo con ese número de año.',
            'year_number.min' => 'El número de año debe ser al menos 1.',
            'end_date.after' => 'La fecha final debe ser posterior a la fecha de inicio.',
        ];
    }
}
