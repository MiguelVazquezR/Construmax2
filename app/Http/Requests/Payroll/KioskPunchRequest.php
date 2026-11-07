<?php

namespace App\Http\Requests\Payroll;

use App\Models\AttendanceLog;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Punch identified with the collaborator's employee number.
 *
 * Kiosk punches store the device location as evidence, so the coordinates are
 * required: a punch without them would be unusable.
 */
class KioskPunchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'employee_number' => ['required', 'string', 'max:50'],
            'type' => ['required', 'string', 'in:'.implode(',', array_keys(AttendanceLog::TYPES))],
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'location_accuracy' => ['nullable', 'numeric', 'min:0'],
            'photo' => ['nullable', 'string', 'max:2000000'],
            // Optional: the Android ID and model of the tablet. They let the
            // server recognize (or register) the device that sent the punch.
            'device_key' => ['nullable', 'string', 'max:255'],
            'device_name' => ['nullable', 'string', 'max:100'],
        ];
    }

    public function messages(): array
    {
        return [
            'employee_number.required' => 'Escribe tu número de empleado.',
            'type.required' => 'Selecciona el tipo de registro.',
            'latitude.required' => 'No se pudo obtener la ubicación del dispositivo. Activa la ubicación e intenta de nuevo.',
            'longitude.required' => 'No se pudo obtener la ubicación del dispositivo. Activa la ubicación e intenta de nuevo.',
        ];
    }
}
