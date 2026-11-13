<?php

namespace App\Http\Controllers\Payroll;

use App\Actions\Payroll\RegisterKioskDeviceAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Payroll\RegisterKioskDeviceRequest;
use App\Services\Payroll\AttendanceDeviceService;
use Illuminate\Http\JsonResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Public registration link for kiosk devices. It lets a tablet or screen be
 * authorized without signing in, protected by a shared password configured
 * from the attendance devices module.
 */
class AttendanceDeviceRegistrationController extends Controller
{
    public function __construct(
        private readonly AttendanceDeviceService $deviceService,
        private readonly RegisterKioskDeviceAction $registerKioskDeviceAction,
    ) {}

    public function show(): Response
    {
        return Inertia::render('Payroll/Devices/Register', [
            'appName' => config('app.name'),
            'kioskUrl' => route('attendance.kiosk.show'),
            'passwordConfigured' => $this->deviceService->registrationPasswordIsConfigured(),
        ]);
    }

    public function store(RegisterKioskDeviceRequest $request): JsonResponse
    {
        $result = $this->registerKioskDeviceAction->execute(
            $request->validated(),
            $request->header('X-Attendance-Device'),
        );

        return response()->json(
            [
                ...$result,
                'kioskUrl' => route('attendance.kiosk.show'),
            ],
            $result['status'] === 'already_authorized' ? 409 : 200,
        );
    }
}
