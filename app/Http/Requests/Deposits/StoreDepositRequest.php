<?php

namespace App\Http\Requests\Deposits;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreDepositRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $isExternal = $this->boolean('is_external');

        $rules = [
            'is_external'              => ['sometimes', 'boolean'],
            'deposit_type_id'          => ['required', 'integer', 'exists:deposit_types,id'],
            'amount'                   => ['required', 'numeric', 'min:0.01'],
            'shift'                    => ['required', 'string', 'in:matutino,vespertino'],
            'scheduled_date'           => ['required', 'date'],
            'notes'                    => ['nullable', 'string', 'max:1000'],
        ];

        if ($isExternal) {
            $rules = array_merge($rules, [
                'technician_id'              => ['nullable', 'integer', 'exists:technicians,id'],
                'technician_bank_account_id' => ['nullable', 'integer', 'exists:technician_bank_accounts,id'],
                'ticket_id'                  => ['nullable', 'integer', 'exists:tickets,id'],
                'external_bank_name'         => ['nullable', 'string', 'max:255'],
                'external_beneficiary_name'  => ['required', 'string', 'max:255'],
                'external_account_number'    => ['nullable', 'string', 'max:50'],
                'external_clabe'             => ['nullable', 'string', 'max:50'],
                'external_card_number'       => ['nullable', 'string', 'max:50'],
                'external_branch_number'     => ['nullable', 'string', 'max:50'],
            ]);
        } else {
            $rules = array_merge($rules, [
                'technician_id'              => ['required', 'integer', 'exists:technicians,id'],
                'technician_bank_account_id' => ['required', 'integer', 'exists:technician_bank_accounts,id'],
                'ticket_id'                  => ['required', 'integer', 'exists:tickets,id'],
            ]);
        }

        return $rules;
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function ($validator) {
            // External deposits must include at least one account identifier
            if ($this->boolean('is_external')) {
                $hasIdentifier = $this->filled('external_account_number')
                    || $this->filled('external_clabe')
                    || $this->filled('external_card_number');

                if (! $hasIdentifier) {
                    $validator->errors()->add(
                        'external_account_number',
                        'Ingresa al menos un número de cuenta, CLABE o tarjeta.'
                    );
                }
            }
        });
    }

    public function messages(): array
    {
        return [
            'technician_id.required'              => 'Selecciona un técnico.',
            'technician_bank_account_id.required' => 'Selecciona una cuenta bancaria.',
            'ticket_id.required'                  => 'Selecciona un ticket.',
            'external_beneficiary_name.required'  => 'Ingresa el nombre del beneficiario.',
            'deposit_type_id.required'            => 'Selecciona un tipo de depósito.',
            'amount.required'                     => 'Ingresa el monto del depósito.',
            'amount.min'                          => 'El monto debe ser mayor a cero.',
            'shift.required'                      => 'Selecciona un turno.',
            'scheduled_date.required'             => 'Selecciona una fecha programada.',
        ];
    }
}