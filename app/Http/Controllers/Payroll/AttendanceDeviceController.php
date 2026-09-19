<?php

namespace App\Http\Controllers\Payroll;

use App\Http\Controllers\Controller;
use App\Models\AttendanceDevice;
use App\Services\Payroll\AttendanceDeviceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AttendanceDeviceController extends Controller
{
    public function __construct(
        private readonly AttendanceDeviceService $deviceService,
    ) {}

    public function index(Request $request): Response
    {
        if (! $request->user()->can('payroll.devices.manage')) {
            abort(403);
        }

        $devices = AttendanceDevice::with(['registeredBy:id,name', 'revokedBy:id,name'])
            ->orderByDesc('id')
            ->get();

        return Inertia::render('Payroll/Devices/Index', [
            'devices' => $devices,
            'kioskUrl' => route('attendance.kiosk.show'),
        ]);
    }

    /**
     * Register the device being used right now (JSON so the page can keep the
     * generated token in localStorage).
     */
    public function store(Request $request): JsonResponse
    {
        if (! $request->user()->can('payroll.devices.manage')) {
            abort(403);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'location' => ['nullable', 'string', 'max:120'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        ['device' => $device, 'token' => $token] = $this->deviceService->register(
            $validated['name'],
            $validated['location'] ?? null,
            $validated['notes'] ?? null,
            $request->user(),
        );

        return response()->json([
            'device' => $device->load('registeredBy:id,name'),
            'token' => $token,
        ]);
    }

    public function destroy(Request $request, AttendanceDevice $device): JsonResponse
    {
        if (! $request->user()->can('payroll.devices.manage')) {
            abort(403);
        }

        if ($device->is_active) {
            $this->deviceService->revoke($device, $request->user());
        }

        return response()->json([
            'device' => $device->fresh()->load(['registeredBy:id,name', 'revokedBy:id,name']),
        ]);
    }
}
