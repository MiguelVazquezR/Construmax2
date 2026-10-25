<?php

namespace App\Http\Controllers\Payroll;

use App\Http\Controllers\Controller;
use App\Services\Payroll\AttendanceDeviceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
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
    ) {}

    public function show(): Response
    {
        return Inertia::render('Payroll/Devices/Register', [
            'appName' => config('app.name'),
            'kioskUrl' => route('attendance.kiosk.show'),
            'passwordConfigured' => $this->deviceService->registrationPasswordIsConfigured(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'location' => ['nullable', 'string', 'max:120'],
            'notes' => ['nullable', 'string', 'max:500'],
            'password' => ['required', 'string'],
        ], [
            'name.required' => 'Escribe el nombre del dispositivo.',
            'password.required' => 'Escribe la contraseña de registro.',
        ]);

        if (! $this->deviceService->registrationPasswordIsConfigured()) {
            throw ValidationException::withMessages([
                'password' => 'El registro público no está activo. Pide al administrador que configure la contraseña de registro.',
            ]);
        }

        if (! $this->deviceService->verifyRegistrationPassword($validated['password'])) {
            throw ValidationException::withMessages([
                'password' => 'Contraseña de registro incorrecta. Verifícala e intenta de nuevo.',
            ]);
        }

        ['device' => $device, 'token' => $token] = $this->deviceService->register(
            $validated['name'],
            $validated['location'] ?? null,
            $validated['notes'] ?? null,
        );

        return response()->json([
            'device' => $device,
            'token' => $token,
            'kioskUrl' => route('attendance.kiosk.show'),
            'message' => 'Dispositivo registrado correctamente.',
        ]);
    }
}
