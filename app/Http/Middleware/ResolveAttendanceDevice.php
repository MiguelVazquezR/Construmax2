<?php

namespace App\Http\Middleware;

use App\Services\Payroll\AttendanceDeviceService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Best-effort device identification for the Android kiosk API.
 *
 * The app is distributed as a controlled APK, so a tablet never has to be
 * authorized: a request without a device token is served like any other
 * client. A token is still honoured when present, because a device registered
 * from the ERP keeps its friendly name and location.
 *
 * The web kiosk (attendance/kiosk/*) keeps using EnsureAttendanceDevice, which
 * does reject unknown devices.
 */
class ResolveAttendanceDevice
{
    public function __construct(
        private readonly AttendanceDeviceService $deviceService,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $device = $this->deviceService->findByToken($request->header('X-Attendance-Device'));

        if ($device) {
            $this->deviceService->touch($device, $request);
        }

        $request->attributes->set('attendance_device', $device);

        return $next($request);
    }
}
