<?php

namespace App\Actions\Jetstream;

use App\Actions\Payroll\RemoveUserFacesAction;
use App\Models\User;
use Laravel\Jetstream\Contracts\DeletesUsers;

class DeleteUser implements DeletesUsers
{
    public function __construct(
        private readonly RemoveUserFacesAction $removeUserFacesAction,
    ) {}

    /**
     * Delete the given user.
     */
    public function delete(User $user): void
    {
        $this->removeUserFacesAction->execute($user);

        $user->deleteProfilePhoto();
        $user->tokens->each->delete();
        $user->delete();
    }
}
