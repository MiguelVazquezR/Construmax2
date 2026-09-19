<?php

namespace App\Http\Requests\Payroll;

use Illuminate\Foundation\Http\FormRequest;

class ReviewVacationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('payroll.vacations.approve');
    }

    public function rules(): array
    {
        return [
            'notes' => ['nullable', 'string', 'max:255'],
        ];
    }
}
