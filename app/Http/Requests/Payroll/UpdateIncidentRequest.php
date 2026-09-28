<?php

namespace App\Http\Requests\Payroll;

use App\Http\Requests\Payroll\Concerns\EnsuresNoIncidentOverlap;
use App\Models\Incident;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateIncidentRequest extends FormRequest
{
    use EnsuresNoIncidentOverlap;

    public function authorize(): bool
    {
        return $this->user()->can('payroll.incidents.manage');
    }

    public function rules(): array
    {
        return [
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'type' => ['required', Rule::in(array_keys(Incident::TYPES))],
            'start_date' => ['required', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'is_paid' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string', 'max:255'],
            'support' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:5120'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(fn () => $this->ensureNoIncidentOverlap($validator));
    }

    public function messages(): array
    {
        return $this->incidentMessages();
    }
}
