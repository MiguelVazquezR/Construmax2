<?php

namespace App\Http\Requests\Deposits;

use Illuminate\Foundation\Http\FormRequest;

class StoreDepositRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('deposits.create');
    }

    public function rules(): array
    {
        return [
            'technician_id'            => ['required', 'integer', 'exists:technicians,id'],
            'technician_bank_account_id' => ['required', 'integer', 'exists:technician_bank_accounts,id'],
            'ticket_id'                => ['required', 'integer', 'exists:tickets,id'],
            'deposit_type_id'          => ['required', 'integer', 'exists:deposit_types,id'],
            'amount'                   => ['required', 'numeric', 'min:0.01'],
            'shift'                    => ['required', 'string', 'in:matutino,vespertino'],
            'scheduled_date'           => ['required', 'date'],
            'notes'                    => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'technician_id.required'             => 'Selecciona un técnico.',
            'technician_bank_account_id.required' => 'Selecciona una cuenta bancaria.',
            'ticket_id.required'                  => 'Selecciona un ticket.',
            'deposit_type_id.required'            => 'Selecciona un tipo de depósito.',
            'amount.required'                     => 'Ingresa el monto del depósito.',
            'amount.min'                          => 'El monto debe ser mayor a cero.',
            'shift.required'                      => 'Selecciona un turno.',
            'scheduled_date.required'             => 'Selecciona una fecha programada.',
        ];
    }
}
