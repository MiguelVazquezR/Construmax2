<?php

namespace App\Actions\Payroll;

use App\Models\FaceEnrollment;
use App\Models\User;
use App\Services\Payroll\FaceRecognition\FaceRecognitionService;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class EnrollUserFacesAction
{
    public function __construct(
        private readonly FaceRecognitionService $faceRecognition,
    ) {}

    /**
     * Replace the active face references of a collaborator with the provided
     * captures (1 to 3 photos). Returns how many faces were indexed.
     *
     * @param  array<int, string>  $photos  base64/data-url captures
     */
    public function execute(User $user, array $photos, ?User $actor = null): int
    {
        if (! $this->faceRecognition->isConfigured()) {
            throw ValidationException::withMessages([
                'photos' => 'El reconocimiento facial aún no está configurado. Captura las credenciales de AWS para activarlo.',
            ]);
        }

        // Index first: when every capture fails the previous references must
        // stay untouched (the collaborator keeps marking with their old face).
        $indexed = [];

        foreach (array_slice($photos, 0, 3) as $photo) {
            if (! is_string($photo) || trim($photo) === '') {
                continue;
            }

            try {
                $indexed[] = $this->faceRecognition->indexFace((string) $user->id, $photo);
            } catch (\Throwable $exception) {
                Log::warning('No se pudo indexar un rostro.', [
                    'user_id' => $user->id,
                    'error' => $exception->getMessage(),
                ]);
            }
        }

        if ($indexed === []) {
            throw ValidationException::withMessages([
                'photos' => 'No se pudo registrar el rostro. Intenta de nuevo con mejor iluminación y sin lentes oscuros.',
            ]);
        }

        // Replace the previous references only after a successful capture.
        $this->removeActive($user);

        $collection = $this->faceRecognition->collectionId();

        foreach ($indexed as $result) {
            FaceEnrollment::create([
                'user_id' => $user->id,
                'collection_id' => $collection,
                'face_id' => $result['face_id'],
                'external_image_id' => (string) $user->id,
                'status' => FaceEnrollment::STATUS_ACTIVE,
                'quality' => $result['quality'],
                'enrolled_by' => $actor?->id,
                'enrolled_at' => now(),
            ]);
        }

        return count($indexed);
    }

    /**
     * Remove the active enrollments (both in Rekognition and locally).
     */
    public function removeActive(User $user): void
    {
        FaceEnrollment::query()
            ->forUser($user->id)
            ->active()
            ->get()
            ->each(function (FaceEnrollment $enrollment) {
                try {
                    $this->faceRecognition->removeFace($enrollment->face_id);
                } catch (\Throwable $exception) {
                    Log::warning('No se pudo eliminar una referencia facial remota.', [
                        'face_id' => $enrollment->face_id,
                        'error' => $exception->getMessage(),
                    ]);
                }

                $enrollment->update(['status' => FaceEnrollment::STATUS_REMOVED]);
            });
    }
}
