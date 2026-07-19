<?php

namespace App\Http\Requests\WorkAcceptanceReports;

use Illuminate\Foundation\Http\FormRequest;

class StoreWorkAcceptanceReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('tickets.index');
    }

    public function rules(): array
    {
        return [
            'ticket_id' => ['required', 'integer', 'exists:tickets,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'ticket_id.required' => 'A ticket is required to generate the work acceptance report.',
            'ticket_id.exists'   => 'The selected ticket does not exist.',
        ];
    }
}
