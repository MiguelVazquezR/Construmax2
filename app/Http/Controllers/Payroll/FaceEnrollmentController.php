<?php

namespace App\Http\Controllers\Payroll;

use App\Actions\Payroll\EnrollUserFacesAction;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class FaceEnrollmentController extends Controller
{
    public function __construct(
        private readonly EnrollUserFacesAction $enrollUserFacesAction,
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

    public function destroy(Request $request, User $user): RedirectResponse
    {
        if (! $request->user()->can('payroll.faces.manage')) {
            abort(403);
        }

        $this->enrollUserFacesAction->removeActive($user);

        return back()->with('success', 'Rostro eliminado. El colaborador podrá registrarlo de nuevo.');
    }
}
