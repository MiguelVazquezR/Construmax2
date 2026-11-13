<?php

namespace App\Actions\Payroll;

use App\Models\AttendanceDevice;
use App\Models\AttendanceLog;
use App\Models\PayrollSetting;
use App\Services\Payroll\AttendanceDeviceService;
use App\Services\Payroll\FaceRecognition\FaceRecognitionService;

/**
 * Context the attendance kiosk needs before a collaborator can register:
 * the authorized device, the punch types and the settings that enable each
 * identification method. The web kiosk and the Android app receive the same
 * payload, so both stay in sync.
 */
class BootstrapKioskAction
{
    public function __construct(
        private readonly AttendanceDeviceService $deviceService,
        private readonly FaceRecognitionService $faceRecognition,
    ) {}

    /**
     * @return array{
     *     device: array{id: int, name: string, location: string|null}|null,
     *     punchTypes: array<string, string>,
     *     faceRecognitionEnabled: bool,
     *     employeeNumberEnabled: bool,
     *     registrationUrl: string,
     *     recoveryPasswordConfigured: bool,
     *     appName: string,
     *     server_time: string,
     *     timezone: string,
     * }
     */
    public function execute(?AttendanceDevice $device, ?string $deviceKey = null): array
    {
        // The device reports its fingerprint on every load: browser updates
        // shift it slightly and the device must stay recognizable.
        if ($device && filled($deviceKey)) {
            $this->deviceService->syncFingerprint($device, $deviceKey);
        }

        return [
            // The Android app needs no authorized device, so this payload can
            // be null when the tablet did not identify itself.
            'device' => $device ? [
                'id' => $device->id,
                'name' => $device->name,
                'location' => $device->location,
            ] : null,
            ...$this->settings(),
            'server_time' => now()->toIso8601String(),
            'timezone' => config('app.timezone'),
        ];
    }

    /**
     * Settings shared with the kiosk page, which renders them without a device
     * token (the device still has to authorize itself before registering).
     *
     * @return array{
     *     punchTypes: array<string, string>,
     *     faceRecognitionEnabled: bool,
     *     employeeNumberEnabled: bool,
     *     registrationUrl: string,
     *     recoveryPasswordConfigured: bool,
     *     appName: string,
     * }
     */
    public function settings(): array
    {
        $settings = PayrollSetting::current();

        return [
            'punchTypes' => AttendanceLog::TYPES,
            // The kiosk always offers the face option; this flag only tells
            // the client whether the biometric provider is ready.
            'faceRecognitionEnabled' => $this->faceRecognition->isConfigured(),
            'employeeNumberEnabled' => (bool) $settings->kiosk_employee_number_enabled,
            'registrationUrl' => route('attendance.devices.register'),
            'recoveryPasswordConfigured' => $this->deviceService->registrationPasswordIsConfigured(),
            'appName' => config('app.name'),
        ];
    }
}
