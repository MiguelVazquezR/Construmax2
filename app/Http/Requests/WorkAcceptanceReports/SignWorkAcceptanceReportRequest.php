<?php

namespace App\Http\Requests\WorkAcceptanceReports;

use Illuminate\Foundation\Http\FormRequest;

class SignWorkAcceptanceReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Any authenticated user can sign (technician presents to branch manager)
        return true;
    }

    public function rules(): array
    {
        return [
            'signature_data'  => ['required', 'string'],
            'signatory_name'  => ['required', 'string', 'max:255'],
            'manager_name'    => ['nullable', 'string', 'max:255'],
            'client_comments' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function messages(): array
    {
        return [
            'signature_data.required' => 'Please provide a signature.',
            'signatory_name.required' => 'Please enter the signatory name.',
            'signatory_name.max'      => 'The signatory name must not exceed 255 characters.',
        ];
    }
}
