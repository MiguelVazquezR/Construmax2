<?php

namespace App\Http\Requests\Payroll;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Kiosk context request. The Android app needs no authorization, so there is
 * no signed-in user to authorize: the device only reports its Android ID and
 * model to stay recognizable in the ERP.
 */
class BootstrapKioskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'device_key' => ['nullable', 'string', 'max:255'],
            'device_name' => ['nullable', 'string', 'max:100'],
        ];
    }
}
