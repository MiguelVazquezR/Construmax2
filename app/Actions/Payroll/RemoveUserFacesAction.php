<?php

namespace App\Actions\Payroll;

use App\Models\FaceEnrollment;
use App\Models\User;
use App\Services\Payroll\FaceRecognition\FaceRecognitionService;
use Illuminate\Support\Facades\Log;

/**
 * Removes every face reference of a collaborator from the provider collection
 * and marks the local records as removed. Used when the collaborator is
 * dismissed, deleted, or loses the photo the reference was indexed from.
 */
class RemoveUserFacesAction
{
    public function __construct(
        private readonly FaceRecognitionService $faceRecognition,
    ) {}

    /**
     * @return int how many references were confirmed as removed
     */
    public function execute(User|int $user): int
    {
        $userId = $user instanceof User ? $user->getKey() : $user;

        $enrollments = FaceEnrollment::query()
            ->forUser($userId)
            ->active()
            ->get();

        $removed = 0;

        foreach ($enrollments as $enrollment) {
            if ($this->removeReference($enrollment)) {
                $removed++;
            }
        }

        return $removed;
    }

    /**
     * The reference stops being current even when the provider fails, so the
     * row is always marked as removed: the leftover face is no longer a valid
     * reference and `rekognition:purge-orphan-faces` retries the deletion.
     */
    private function removeReference(FaceEnrollment $enrollment): bool
    {
        $removed = true;

        try {
            $this->faceRecognition->removeFace($enrollment->face_id);
        } catch (\Throwable $exception) {
            $removed = false;

            Log::warning('No se pudo eliminar una referencia facial remota.', [
                'face_id' => $enrollment->face_id,
                'user_id' => $enrollment->user_id,
                'error' => $exception->getMessage(),
            ]);
        }

        $enrollment->update(['status' => FaceEnrollment::STATUS_REMOVED]);

        return $removed;
    }
}
