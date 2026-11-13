<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Payroll\BootstrapKioskAction;
use App\Actions\Payroll\BuildPunchResponseAction;
use App\Actions\Payroll\RecoverKioskDeviceAction;
use App\Actions\Payroll\RegisterEmployeeNumberPunchAction;
use App\Actions\Payroll\RegisterFacePunchAction;
use App\Actions\Payroll\RegisterKioskDeviceAction;
use App\Actions\Payroll\ResolveKioskDeviceAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Payroll\BootstrapKioskRequest;
use App\Http\Requests\Payroll\KioskFacePunchRequest;
use App\Http\Requests\Payroll\KioskPunchRequest;
use App\Http\Requests\Payroll\RecoverKioskDeviceRequest;
use App\Http\Requests\Payroll\RegisterKioskDeviceRequest;
use App\Models\AttendanceDevice;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * JSON API for the Android attendance kiosk app.
 *
 * No device authorization is required: the app is installed manually from a
 * controlled APK, so every endpoint works for any tablet. The optional
 * "attendance.device.optional" middleware still recognizes a tablet that
 * carries a device token, and ResolveKioskDeviceAction registers the tablet on
 * first contact from its Android ID so punches stay traceable.
 */
class KioskController extends Controller
{
    public function __construct(
        private readonly BootstrapKioskAction $bootstrapKioskAction,
        private readonly RecoverKioskDeviceAction $recoverKioskDeviceAction,
        private readonly RegisterKioskDeviceAction $registerKioskDeviceAction,
        private readonly RegisterEmployeeNumberPunchAction $employeeNumberPunchAction,
        private readonly RegisterFacePunchAction $facePunchAction,
        private readonly BuildPunchResponseAction $buildPunchResponseAction,
        private readonly ResolveKioskDeviceAction $resolveKioskDeviceAction,
    ) {}

    /**
     * Light connectivity probe: detects a lost connection and compares the
     * device clock with the server one without loading the whole context.
     */
    public function ping(Request $request): JsonResponse
    {
        return response()->json([
            'status' => 'ok',
            'device' => $this->devicePayload($this->device($request)),
            'server_time' => now()->toIso8601String(),
        ]);
    }

    /**
     * Return the kiosk context: the device, the punch types and the settings
     * that enable each identification method.
     */
    public function bootstrap(BootstrapKioskRequest $request): JsonResponse
    {
        $data = $request->validated();

        return response()->json($this->bootstrapKioskAction->execute(
            $this->resolveDevice($request, $data['device_key'] ?? null, $data['device_name'] ?? null),
            $data['device_key'] ?? null,
        ));
    }

    /**
     * Give a tablet its ERP registration back (optional for the app, which
     * needs no authorization).
     */
    public function recover(RecoverKioskDeviceRequest $request): JsonResponse
    {
        return response()->json(
            $this->recoverKioskDeviceAction->execute($request->validated(), $request)
        );
    }

    /**
     * Register a punch identified with the collaborator's employee number.
     */
    public function punch(KioskPunchRequest $request): JsonResponse
    {
        $data = $request->validated();

        $log = $this->employeeNumberPunchAction->execute(
            $data,
            $this->resolveDevice($request, $data['device_key'] ?? null, $data['device_name'] ?? null),
        );

        return response()->json($this->buildPunchResponseAction->execute($log));
    }

    /**
     * Register a punch identified with facial recognition.
     */
    public function facePunch(KioskFacePunchRequest $request): JsonResponse
    {
        $data = $request->validated();

        ['log' => $log, 'similarity' => $similarity] = $this->facePunchAction->execute(
            $data,
            $this->resolveDevice($request, $data['device_key'] ?? null, $data['device_name'] ?? null),
        );

        return response()->json($this->buildPunchResponseAction->execute($log, $similarity));
    }

    /**
     * Give the tablet an ERP registration (friendly name and location) with the
     * shared registration password. Optional for the app.
     */
    public function registerDevice(RegisterKioskDeviceRequest $request): JsonResponse
    {
        $result = $this->registerKioskDeviceAction->execute(
            $request->validated(),
            $request->header('X-Attendance-Device'),
        );

        $status = $result['status'] === 'already_authorized' ? 409 : 201;

        return response()->json($result, $status);
    }

    private function device(Request $request): ?AttendanceDevice
    {
        return $request->attributes->get('attendance_device');
    }

    private function resolveDevice(Request $request, ?string $deviceKey, ?string $deviceName): ?AttendanceDevice
    {
        return $this->resolveKioskDeviceAction->execute(
            $this->device($request),
            $deviceKey,
            $deviceName,
            $request,
        );
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
