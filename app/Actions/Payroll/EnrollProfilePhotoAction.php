<?php

namespace App\Actions\Payroll;

use App\Models\PayrollSetting;
use App\Models\User;
use App\Services\Payroll\FaceRecognition\FaceRecognitionService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Indexes an uploaded profile photo as the face reference of a collaborator,
 * so the attendance kiosk can identify them. It never throws: when the module
 * is not ready it returns "not_applicable" and when the face cannot be indexed
 * it returns "failed", letting the caller decide how to report it.
 */
class EnrollProfilePhotoAction
{
    public const RESULT_ENROLLED = 'enrolled';

    public const RESULT_NOT_APPLICABLE = 'not_applicable';

    public const RESULT_FAILED = 'failed';

    public function __construct(
        private readonly FaceRecognitionService $faceRecognition,
        private readonly EnrollUserFacesAction $enrollUserFacesAction,
    ) {}

    /**
     * @param  string  $imagePath  absolute path of the portrait (or a base64/data URL capture)
     */
    public function execute(User $user, string $imagePath, ?User $actor = null): string
    {
        $profile = $user->payrollProfile()->first();

        if (! $profile || ! $profile->is_attendance_subject) {
            return self::RESULT_NOT_APPLICABLE;
        }

        $settings = PayrollSetting::current();

        if (! $settings->face_recognition_enabled || ! $this->faceRecognition->isConfigured()) {
            return self::RESULT_NOT_APPLICABLE;
        }

        try {
            // A single capture replaces the previous references; the action only
            // removes them after the new face is successfully indexed.
            $this->enrollUserFacesAction->execute($user, [$this->toDataUrl($imagePath)], $actor);

            return self::RESULT_ENROLLED;
        } catch (\Throwable $exception) {
            Log::warning('No se pudo registrar la foto de perfil como referencia facial.', [
                'user_id' => $user->id,
                'error' => $exception->getMessage(),
            ]);

            return self::RESULT_FAILED;
        }
    }

    /**
     * Enroll the face from the profile photo already stored for the user, which
     * is what happens when a collaborator is hired again. Returns
     * "not_applicable" when the collaborator has no portrait to index.
     */
    public function enrollFromStoredPhoto(User $user, ?User $actor = null): string
    {
        $disk = Storage::disk(config('jetstream.profile_photo_disk', 'public'));

        if (! $user->profile_photo_path || ! $disk->exists($user->profile_photo_path)) {
            return self::RESULT_NOT_APPLICABLE;
        }

        $photo = 'data:image/jpeg;base64,'.base64_encode((string) $disk->get($user->profile_photo_path));

        return $this->execute($user, $photo, $actor);
    }

    /**
     * Accepts an absolute file path, a data URL or raw base64.
     */
    private function toDataUrl(string $imagePath): string
    {
        if (! is_file($imagePath)) {
            return $imagePath;
        }

        $mime = mime_content_type($imagePath) ?: 'image/jpeg';

        return 'data:'.$mime.';base64,'.base64_encode((string) file_get_contents($imagePath));
    }
}
