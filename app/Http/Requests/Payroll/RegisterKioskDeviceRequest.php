<?php

namespace App\Http\Requests\Payroll;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Public registration of a kiosk device, protected by the shared registration
 * password configured in the attendance devices module.
 */
class RegisterKioskDeviceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'location' => ['nullable', 'string', 'max:120'],
            'notes' => ['nullable', 'string', 'max:500'],
            'password' => ['required', 'string'],
            'device_key' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Escribe el nombre del dispositivo.',
            'password.required' => 'Escribe la contraseña de registro.',
        ];
    }
}
