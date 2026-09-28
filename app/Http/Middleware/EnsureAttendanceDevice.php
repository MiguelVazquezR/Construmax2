<?php

namespace App\Http\Middleware;

use App\Services\Payroll\AttendanceDeviceService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Restrict the attendance kiosk API to devices registered inside the ERP.
 * The device sends its plain token in the X-Attendance-Device header.
 */
class EnsureAttendanceDevice
{
    public function __construct(
        private readonly AttendanceDeviceService $deviceService,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->header('X-Attendance-Device') ?: $request->input('device_token');
        $device = $this->deviceService->findByToken($token);

        if (! $device) {
            abort(403, 'Dispositivo no autorizado.');
        }

        $this->deviceService->touch($device, $request);
        $request->attributes->set('attendance_device', $device);

        return $next($request);
    }
}
