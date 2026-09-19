<?php

namespace App\Console\Commands;

use App\Actions\Payroll\EnrollProfilePhotoAction;
use App\Models\FaceEnrollment;
use App\Models\PayrollProfile;
use App\Models\PayrollSetting;
use App\Services\Payroll\FaceRecognition\FaceRecognitionService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class EnrollProfilePhotos extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'payroll:enroll-profile-photos
        {--user= : Registrar solo la foto del colaborador con este id de usuario}
        {--force : Volver a registrar la foto aunque ya tenga un rostro activo}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Registra las fotos de perfil de los colaboradores como referencia facial del kiosco';

    /**
     * Execute the console command.
     */
    public function handle(
        EnrollProfilePhotoAction $enrollProfilePhotoAction,
        FaceRecognitionService $faceRecognition,
    ): int {
        if (! $faceRecognition->isConfigured()) {
            $this->error('El reconocimiento facial no está configurado: agrega las credenciales de AWS en el archivo .env.');

            return self::FAILURE;
        }

        if (! PayrollSetting::current()->face_recognition_enabled) {
            $this->error('El reconocimiento facial está desactivado. Actívalo en Recursos Humanos → Configuración de nómina.');

            return self::FAILURE;
        }

        $disk = Storage::disk(config('jetstream.profile_photo_disk', 'public'));

        $profiles = PayrollProfile::query()
            ->where('is_attendance_subject', true)
            ->with('user')
            ->when($this->option('user'), fn ($query, $userId) => $query->where('user_id', (int) $userId))
            ->get();

        $enrolled = 0;
        $skipped = 0;
        $failed = 0;

        foreach ($profiles as $profile) {
            $user = $profile->user;

            if (! $user) {
                $skipped++;

                continue;
            }

            $hasActiveFace = FaceEnrollment::query()
                ->forUser($user->id)
                ->active()
                ->exists();

            if ($hasActiveFace && ! $this->option('force')) {
                $skipped++;

                continue;
            }

            if (! $user->profile_photo_path || ! $disk->exists($user->profile_photo_path)) {
                $skipped++;

                continue;
            }

            $photo = 'data:image/jpeg;base64,'.base64_encode((string) $disk->get($user->profile_photo_path));

            $result = $enrollProfilePhotoAction->execute($user, $photo);

            match ($result) {
                EnrollProfilePhotoAction::RESULT_ENROLLED => $enrolled++,
                EnrollProfilePhotoAction::RESULT_FAILED => $failed++,
                default => $skipped++,
            };
        }

        $this->info("Rostros registrados: {$enrolled} · Omitidos: {$skipped} · Fallidos: {$failed}.");

        return self::SUCCESS;
    }
}
