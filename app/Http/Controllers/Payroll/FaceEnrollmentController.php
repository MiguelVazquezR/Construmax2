<?php

namespace App\Http\Controllers\Payroll;

use App\Actions\Payroll\EnrollUserFacesAction;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Payroll\FaceRecognition\FaceRecognitionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class FaceEnrollmentController extends Controller
{
    public function __construct(
        private readonly EnrollUserFacesAction $enrollUserFacesAction,
        private readonly FaceRecognitionService $faceRecognition,
    ) {}

    /**
     * Enroll (or re-enroll) the face of any collaborator (admin flow).
     */
    public function store(Request $request, User $user): RedirectResponse
    {
        if (! $request->user()->can('payroll.faces.manage')) {
            abort(403);
        }

        $validated = $request->validate([
            'photos' => ['required', 'array', 'min:1', 'max:3'],
            'photos.*' => ['required', 'string', 'max:2000000'],
        ]);

        $this->enrollUserFacesAction->execute($user, $validated['photos'], $request->user());

        return back()->with('success', 'Rostro registrado correctamente.');
    }

    /**
     * Enroll the own face (self-service, requires attendance enabled).
     */
    public function storeSelf(Request $request): RedirectResponse
    {
        $user = $request->user();

        if (! $user->payrollProfile?->is_attendance_subject) {
            throw ValidationException::withMessages([
                'photos' => 'Tu asistencia no está habilitada. Contacta al administrador.',
            ]);
        }

        $validated = $request->validate([
            'photos' => ['required', 'array', 'min:1', 'max:3'],
            'photos.*' => ['required', 'string', 'max:2000000'],
        ]);

        $this->enrollUserFacesAction->execute($user, $validated['photos'], $user);

        return back()->with('success', 'Rostro registrado correctamente.');
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        if (! $request->user()->can('payroll.faces.manage')) {
            abort(403);
        }

        $this->enrollUserFacesAction->removeActive($user);

        return back()->with('success', 'Rostro eliminado. El colaborador podrá registrarlo de nuevo.');
    }

    public function destroySelf(Request $request): RedirectResponse
    {
        $this->enrollUserFacesAction->removeActive($request->user());

        return back()->with('success', 'Rostro eliminado.');
    }

    /**
     * Whether the provider is ready (used by the self-service page).
     */
    public function status(Request $request): \Illuminate\Http\JsonResponse
    {
        return response()->json([
            'configured' => $this->faceRecognition->isConfigured(),
        ]);
    }
}
