<?php

namespace App\Actions\Payroll;

use App\Services\Payroll\AttendanceDeviceService;
use Illuminate\Validation\ValidationException;

/**
 * Authorize a new kiosk device with the shared registration password.
 *
 * The plain token is returned once and must be stored in the device (browser
 * storage or the Android keystore); the database keeps only its hash.
 */
class RegisterKioskDeviceAction
{
    public function __construct(
        private readonly AttendanceDeviceService $deviceService,
    ) {}

    /**
     * @param  array{name: string, location?: string|null, notes?: string|null, password: string, device_key?: string|null}  $data
     * @return array{
     *     status: string,
     *     device: array{id: int, name: string, location: string|null},
     *     token?: string,
     *     message: string,
     * }
     */
    public function execute(array $data, ?string $currentDeviceToken = null): array
    {
        if (! $this->deviceService->registrationPasswordIsConfigured()) {
            throw ValidationException::withMessages([
                'password' => 'El registro público no está activo. Pide al administrador que configure la contraseña de registro.',
            ]);
        }

        if (! $this->deviceService->verifyRegistrationPassword($data['password'])) {
            throw ValidationException::withMessages([
                'password' => 'Contraseña de registro incorrecta. Verifícala e intenta de nuevo.',
            ]);
        }

        // A device that already holds a valid token is authorized: registering
        // it again would create a duplicate.
        $currentDevice = $this->deviceService->findByToken($currentDeviceToken);

        if ($currentDevice) {
            return [
                'status' => 'already_authorized',
                'device' => [
                    'id' => $currentDevice->id,
                    'name' => $currentDevice->name,
                    'location' => $currentDevice->location,
                ],
                'message' => "Este dispositivo ya está autorizado como «{$currentDevice->name}». No es necesario registrarlo de nuevo.",
            ];
        }

        ['device' => $device, 'token' => $token] = $this->deviceService->register(
            $data['name'],
            $data['location'] ?? null,
            $data['notes'] ?? null,
            null,
            $data['device_key'] ?? null,
        );

        return [
            'status' => 'registered',
            'device' => [
                'id' => $device->id,
                'name' => $device->name,
                'location' => $device->location,
            ],
            'token' => $token,
            'message' => 'Dispositivo registrado correctamente.',
        ];
    }
}
