<?php

namespace App\Actions\Payroll;

use App\Models\AttendanceDevice;
use App\Services\Payroll\AttendanceDeviceService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * Recognize a device that lost its token (cleared storage, reinstalled app,
 * replaced token) without registering it again.
 *
 * The device is recognized automatically when its fingerprint matches a
 * registered device that still uses the same network it used before. Any other
 * case requires the shared registration password, because a fingerprint is not
 * a secret.
 */
class RecoverKioskDeviceAction
{
    public function __construct(
        private readonly AttendanceDeviceService $deviceService,
    ) {}

    /**
     * @param  array{device_key: string, password?: string|null, device_id?: int|null}  $data
     * @return array{
     *     status: string,
     *     device: array{id: int, name: string, location: string|null}|null,
     *     devices?: array<int, array{id: int, name: string, location: string|null}>,
     *     token?: string,
     *     message?: string,
     * }
     */
    public function execute(array $data, Request $request): array
    {
        $ip = $request->ip();
        $device = $this->deviceService->bestMatchForFingerprint($data['device_key'], $ip);

        if (filled($data['password'] ?? null)) {
            return $this->recoverWithPassword($data, $device, $request);
        }

        if ($device && $this->deviceService->isOnKnownNetwork($device, $ip)) {
            return $this->authorizeDevice($device, $data['device_key'], $request);
        }

        if (! $this->deviceService->registrationPasswordIsConfigured()) {
            return [
                'status' => 'unavailable',
                'device' => null,
                'message' => 'La reactivación de dispositivos está desactivada. Pide al administrador que configure la contraseña de registro en Dispositivos de asistencia.',
            ];
        }

        return [
            'status' => 'password_required',
            'device' => $this->devicePayload($device),
            'message' => $device
                ? 'Este dispositivo ya estaba registrado. Escribe la contraseña de registro para reactivarlo.'
                : 'Escribe la contraseña de registro para reactivar un dispositivo ya registrado.',
        ];
    }

    /**
     * @param  array{device_key: string, password?: string|null, device_id?: int|null}  $data
     * @return array{status: string, device: array{id: int, name: string, location: string|null}|null, devices?: array<int, array{id: int, name: string, location: string|null}>, token?: string, message?: string}
     */
    private function recoverWithPassword(array $data, ?AttendanceDevice $device, Request $request): array
    {
        if (! $this->deviceService->registrationPasswordIsConfigured()) {
            throw ValidationException::withMessages([
                'password' => 'La reactivación de dispositivos está desactivada. Pide al administrador que configure la contraseña de registro.',
            ]);
        }

        if (! $this->deviceService->verifyRegistrationPassword($data['password'])) {
            throw ValidationException::withMessages([
                'password' => 'Contraseña de registro incorrecta. Verifícala e intenta de nuevo.',
            ]);
        }

        if (filled($data['device_id'] ?? null)) {
            $device = AttendanceDevice::active()->find($data['device_id']);

            if (! $device) {
                throw ValidationException::withMessages([
                    'device_id' => 'El dispositivo seleccionado ya no está autorizado. Pide al administrador que lo revise.',
                ]);
            }
        }

        if (! $device) {
            // The fingerprint does not point to a single device (a browser
            // update or an app reinstall changes it): the operator picks the
            // right one from the active devices.
            return [
                'status' => 'choose_device',
                'device' => null,
                'devices' => $this->deviceService->authorizableDevices(),
                'message' => 'Elige el dispositivo que estás usando para reactivarlo.',
            ];
        }

        return $this->authorizeDevice($device, $data['device_key'], $request);
    }

    /**
     * @return array{status: string, device: array{id: int, name: string, location: string|null}, token: string}
     */
    private function authorizeDevice(AttendanceDevice $device, ?string $deviceKey, Request $request): array
    {
        $token = $this->deviceService->issueToken($device);

        $this->deviceService->syncFingerprint($device, $deviceKey);
        $this->deviceService->touch($device, $request);

        Log::info('Attendance kiosk device recognized again.', [
            'attendance_device_id' => $device->id,
            'ip' => $request->ip(),
        ]);

        return [
            'status' => 'authorized',
            'device' => $this->devicePayload($device),
            'token' => $token,
        ];
    }

    /**
     * @return array{id: int, name: string, location: string|null}|null
     */
    private function devicePayload(?AttendanceDevice $device): ?array
    {
        if (! $device) {
            return null;
        }

        return [
            'id' => $device->id,
            'name' => $device->name,
            'location' => $device->location,
        ];
    }
}
