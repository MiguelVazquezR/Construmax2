<?php

namespace App\Http\Requests\Payroll;

use App\Models\Holiday;
use Closure;
use Illuminate\Foundation\Http\FormRequest;

class StoreHolidayRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('payroll.holidays.manage');
    }

    protected function prepareForValidation(): void
    {
        if ($this->filled('date')) {
            $this->merge(['year' => (int) substr((string) $this->input('date'), 0, 4)]);
        }
    }

    public function rules(): array
    {
        return [
            'date' => [
                'required',
                'date',
                // Driver-safe duplicate check: date casts keep a time part on some drivers.
                function (string $attribute, mixed $value, Closure $fail) {
                    if (Holiday::query()->whereDate('date', $value)->exists()) {
                        $fail('Ya existe un día festivo registrado en esa fecha.');
                    }
                },
            ],
            'name' => ['required', 'string', 'max:150'],
            'is_mandatory' => ['sometimes', 'boolean'],
            'apply_extra_pay' => ['sometimes', 'boolean'],
            'notes' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'date.required' => 'Selecciona la fecha del día festivo.',
            'name.required' => 'El nombre del día festivo es obligatorio.',
        ];
    }
}
