<?php

namespace App\Http\Controllers\Payroll;

use App\Actions\Payroll\BootstrapKioskAction;
use App\Actions\Payroll\BuildPunchResponseAction;
use App\Actions\Payroll\RecoverKioskDeviceAction;
use App\Actions\Payroll\RegisterEmployeeNumberPunchAction;
use App\Actions\Payroll\RegisterFacePunchAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Payroll\BootstrapKioskRequest;
use App\Http\Requests\Payroll\KioskFacePunchRequest;
use App\Http\Requests\Payroll\KioskPunchRequest;
use App\Http\Requests\Payroll\RecoverKioskDeviceRequest;
use App\Models\AttendanceDevice;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Public attendance kiosk rendered in the browser.
 *
 * Every API call is restricted by the "attendance.device" middleware to
 * registered devices. The use cases are shared with the Android kiosk API
 * (App\Http\Controllers\Api\V1\KioskController), so both clients behave the
 * same way.
 */
class KioskController extends Controller
{
    public function __construct(
        private readonly BootstrapKioskAction $bootstrapKioskAction,
        private readonly RecoverKioskDeviceAction $recoverKioskDeviceAction,
        private readonly RegisterEmployeeNumberPunchAction $employeeNumberPunchAction,
        private readonly RegisterFacePunchAction $facePunchAction,
        private readonly BuildPunchResponseAction $buildPunchResponseAction,
    ) {}

    public function show(): Response
    {
        return Inertia::render('Payroll/Kiosk/Index', $this->bootstrapKioskAction->settings());
    }

    /**
     * Validate the stored device token and return the kiosk context.
     */
    public function bootstrap(BootstrapKioskRequest $request): JsonResponse
    {
        return response()->json(
            $this->bootstrapKioskAction->execute($this->device($request), $request->validated()['device_key'] ?? null)
        );
    }

    /**
     * Recognize a device that lost its token (cleared browsing data, browser
     * storage eviction, iOS storage cleanup) without registering it again.
     *
     * The device is recognized automatically when its fingerprint matches a
     * registered device that still uses the same network it used before. Any
     * other case requires the shared registration password, the same one used
     * by the public registration link, because a fingerprint is not a secret.
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
        $log = $this->employeeNumberPunchAction->execute(
            $request->validated(),
            $this->device($request),
        );

        return response()->json($this->buildPunchResponseAction->execute($log));
    }

    /**
     * Register a punch identified with facial recognition (1:N search).
     */
    public function facePunch(KioskFacePunchRequest $request): JsonResponse
    {
        ['log' => $log, 'similarity' => $similarity] = $this->facePunchAction->execute(
            $request->validated(),
            $this->device($request),
        );

        return response()->json($this->buildPunchResponseAction->execute($log, $similarity));
    }

    private function device(Request $request): AttendanceDevice
    {
        return $request->attributes->get('attendance_device');
    }
}
