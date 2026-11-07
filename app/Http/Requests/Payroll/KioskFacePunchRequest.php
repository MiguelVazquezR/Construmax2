<?php

namespace App\Http\Requests\Payroll;

use App\Models\AttendanceLog;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Punch identified with facial recognition. The photo arrives as a base64
 * data URL (data:image/jpeg;base64,...) captured by the client camera.
 *
 * Kiosk punches store the device location as evidence, so the coordinates are
 * required: a punch without them would be unusable.
 */
class KioskFacePunchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'type' => ['required', 'string', 'in:'.implode(',', array_keys(AttendanceLog::TYPES))],
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'location_accuracy' => ['nullable', 'numeric', 'min:0'],
            'photo' => ['required', 'string', 'max:2000000'],
            // Optional: the Android ID and model of the tablet. They let the
            // server recognize (or register) the device that sent the punch.
            'device_key' => ['nullable', 'string', 'max:255'],
            'device_name' => ['nullable', 'string', 'max:100'],
        ];
    }

    public function messages(): array
    {
        return [
            'type.required' => 'Selecciona el tipo de registro.',
            'photo.required' => 'No se pudo capturar tu foto. Intenta de nuevo.',
            'photo.max' => 'La foto es demasiado grande para enviarla. Colócate más cerca de la cámara e intenta de nuevo.',
            'latitude.required' => 'No se pudo obtener la ubicación del dispositivo. Activa la ubicación e intenta de nuevo.',
            'longitude.required' => 'No se pudo obtener la ubicación del dispositivo. Activa la ubicación e intenta de nuevo.',
        ];
    }
}
