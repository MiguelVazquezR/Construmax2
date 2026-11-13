<?php

namespace App\Actions\Payroll;

use App\Models\AttendanceDevice;
use App\Services\Payroll\AttendanceDeviceService;
use Illuminate\Http\Request;

/**
 * Resolve the tablet that is sending a request to the Android kiosk API.
 *
 * The app never needs an authorized device: it is installed manually from a
 * controlled APK. When the request carries no device token the tablet is
 * recognized by its Android ID (`device_key`) and the device row is created on
 * first contact, so every punch stays traceable in the ERP.
 */
class ResolveKioskDeviceAction
{
    public function __construct(
        private readonly AttendanceDeviceService $deviceService,
    ) {}

    public function execute(
        ?AttendanceDevice $device,
        ?string $deviceKey,
        ?string $deviceName,
        Request $request,
    ): ?AttendanceDevice {
        $device ??= $this->deviceService->findOrCreateForApp($deviceKey, $deviceName);

        if ($device) {
            $this->deviceService->touch($device, $request);
        }

        return $device;
    }
}
