<?php

namespace App\Actions\Payroll;

use App\Models\User;

/**
 * Clears the profile photo of a collaborator and removes the face references
 * indexed from it, so the attendance kiosk stops recognizing that portrait.
 */
class RemoveProfilePhotoAction
{
    public function __construct(
        private readonly EnrollUserFacesAction $enrollUserFacesAction,
    ) {}

    public function execute(User $user): void
    {
        $this->enrollUserFacesAction->removeActive($user);

        $user->deleteProfilePhoto();
    }
}
