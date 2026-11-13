<?php

namespace App\Http\Requests\Payroll;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Public kiosk endpoint: a device that lost its token sends its fingerprint
 * and, when the server cannot recognize it by itself, the shared registration
 * password.
 */
class RecoverKioskDeviceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'device_key' => ['required', 'string', 'max:255'],
            'password' => ['nullable', 'string', 'max:100'],
            'device_id' => ['nullable', 'integer'],
        ];
    }

    public function messages(): array
    {
        return [
            'device_key.required' => 'No se pudo identificar este dispositivo. Recarga la página e intenta de nuevo.',
        ];
    }
}
