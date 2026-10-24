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
            'registrationUrl' => route('attendance.devices.register'),
            'registrationPassword' => $this->deviceService->registrationPassword(),
            'registrationPasswordConfigured' => $this->deviceService->registrationPasswordIsConfigured(),
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

    /**
     * Update the descriptive data of a device.
     */
    public function update(Request $request, AttendanceDevice $device): JsonResponse
    {
        if (! $request->user()->can('payroll.devices.manage')) {
            abort(403);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'location' => ['nullable', 'string', 'max:120'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $this->deviceService->update($device, $validated);

        return response()->json([
            'device' => $device->fresh()->load(['registeredBy:id,name', 'revokedBy:id,name']),
        ]);
    }

    /**
     * Replace the shared password used by the public registration link.
     */
    public function updateRegistrationPassword(Request $request): JsonResponse
    {
        if (! $request->user()->can('payroll.devices.manage')) {
            abort(403);
        }

        $validated = $request->validate([
            'password' => ['required', 'string', 'min:3', 'max:100'],
        ], [
            'password.required' => 'Escribe la nueva contraseña de registro.',
            'password.min' => 'La contraseña debe tener al menos 3 caracteres.',
        ]);

        $this->deviceService->setRegistrationPassword($validated['password']);

        return response()->json(['configured' => true]);
    }

    public function revoke(Request $request, AttendanceDevice $device): JsonResponse
    {
        if (! $request->user()->can('payroll.devices.manage')) {
            abort(403);
        }

        $this->deviceService->revoke($device, $request->user());

        return response()->json([
            'device' => $device->fresh()->load(['registeredBy:id,name', 'revokedBy:id,name']),
        ]);
    }

    /**
     * Reactivate a revoked device. Its original token keeps working, so the
     * browser that had it stored can use the kiosk again.
     */
    public function restore(Request $request, AttendanceDevice $device): JsonResponse
    {
        if (! $request->user()->can('payroll.devices.manage')) {
            abort(403);
        }

        $this->deviceService->restore($device);

        return response()->json([
            'device' => $device->fresh()->load(['registeredBy:id,name', 'revokedBy:id,name']),
        ]);
    }

    /**
     * Delete the device permanently. The token stops working immediately.
     */
    public function destroy(Request $request, AttendanceDevice $device): JsonResponse
    {
        if (! $request->user()->can('payroll.devices.manage')) {
            abort(403);
        }

        $this->deviceService->delete($device);

        return response()->json(['deleted' => true]);
    }
}
