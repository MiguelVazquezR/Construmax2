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
        private readonly RemoveUserFacesAction $removeUserFacesAction,
    ) {}

    public function execute(User $user): void
    {
        $this->removeUserFacesAction->execute($user);

        $user->deleteProfilePhoto();
    }
}
