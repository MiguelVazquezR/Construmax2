<?php

namespace App\Console\Commands;

use App\Models\FaceEnrollment;
use App\Models\User;
use App\Services\Payroll\FaceRecognition\FaceRecognitionService;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

/**
 * Rekognition bills every indexed face monthly, so a face left behind by a
 * collaborator that no longer exists is pure cost, and so is a second face of
 * the same collaborator (usually left by a replaced portrait). This command
 * lists those faces and, with `--delete`, removes them.
 *
 * A missing collaborator record is always enough to delete. Being inactive,
 * having a termination date or not registering attendance is NOT enough (the
 * person may come back), so those faces are kept. Duplicates are only removed
 * with `--include-duplicates` and only when the collaborator keeps another face
 * with an active reference.
 */
class PurgeOrphanFaces extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'rekognition:purge-orphan-faces
        {--delete : Elimina los rostros encontrados (sin la opción solo se listan)}
        {--include-duplicates : Incluye los rostros duplicados de colaboradores con más de un rostro (conserva el de la referencia activa)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Lista los rostros de la colección de Rekognition cuyos colaboradores ya no existen (y los duplicados con --include-duplicates) y, con --delete, los elimina';

    /**
     * Execute the console command.
     */
    public function handle(FaceRecognitionService $faceRecognition): int
    {
        if (! $faceRecognition->isConfigured()) {
            $this->error('El reconocimiento facial no está configurado: agrega las credenciales de AWS en el archivo .env.');

            return self::FAILURE;
        }

        $collection = $faceRecognition->collectionId();

        try {
            $faces = $faceRecognition->listFaces();
        } catch (\Throwable $exception) {
            $this->error("No se pudo leer la colección «{$collection}»: {$exception->getMessage()}");
            $this->reportConnectionHint($exception->getMessage());

            return self::FAILURE;
        }

        if ($faces === []) {
            $this->info("La colección «{$collection}» no tiene rostros registrados.");

            return self::SUCCESS;
        }

        [$orphans, $kept] = $this->classify($faces, $collection);

        if ($kept > 0) {
            $this->line("Se conservan {$kept} rostros de colaboradores que sí existen (activos, dados de baja o sin asistencia).");
        }

        if ($orphans === []) {
            $this->info("No se encontraron rostros a eliminar entre los ".count($faces)." rostros de la colección «{$collection}».");

            return self::SUCCESS;
        }

        $this->table(
            ['Face id', 'Colaborador', 'Motivo'],
            array_map(
                fn (array $orphan) => [$orphan['face_id'], $orphan['collaborator'], $orphan['reason']],
                $orphans,
            ),
        );

        if (! $this->option('delete')) {
            $this->warn(count($orphans).' rostros encontrados. Ejecuta el comando con --delete para eliminarlos.');

            return self::SUCCESS;
        }

        try {
            $deleted = $faceRecognition->removeFaces(array_column($orphans, 'face_id'));
        } catch (\Throwable $exception) {
            $this->error("No se pudieron eliminar los rostros de la colección «{$collection}»: {$exception->getMessage()}");
            $this->reportConnectionHint($exception->getMessage());

            return self::FAILURE;
        }

        if ($deleted !== []) {
            FaceEnrollment::query()
                ->whereIn('face_id', $deleted)
                ->where('status', FaceEnrollment::STATUS_ACTIVE)
                ->update(['status' => FaceEnrollment::STATUS_REMOVED]);
        }

        $pending = array_values(array_diff(array_column($orphans, 'face_id'), $deleted));

        $this->info(count($deleted)." rostros eliminados de la colección «{$collection}».");

        if ($pending !== []) {
            $this->warn('El proveedor no confirmó la eliminación de '.count($pending).' rostros: '.implode(', ', $pending));

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    /**
     * Splits the collection faces into the ones that can be deleted and the
     * ones that must be kept.
     *
     * @param  array<int, array{face_id: string, external_image_id: string, image_id: string}>  $faces
     * @return array{0: array<int, array{face_id: string, collaborator: string, reason: string}>, 1: int}
     */
    private function classify(array $faces, string $collection): array
    {
        $users = $this->usersByExternalId($faces);
        $activeReferences = $this->activeReferences($collection);
        $facesPerCollaborator = collect($faces)->countBy('external_image_id');

        $orphans = [];
        $kept = 0;

        foreach ($faces as $face) {
            $reason = $this->orphanReason($face, $users)
                ?? $this->duplicateReason($face, $activeReferences, $facesPerCollaborator);

            if ($reason === null) {
                $kept++;

                continue;
            }

            $orphans[] = [
                'face_id' => $face['face_id'],
                'collaborator' => $this->collaboratorLabel($face['external_image_id'], $users),
                'reason' => $reason,
            ];
        }

        return [$orphans, $kept];
    }

    /**
     * A face only loses its purpose when the collaborator row it references is
     * gone (or was deleted from the app). Being inactive, terminated or without
     * attendance does not delete the face: the person can come back, and that
     * decision belongs to the collaborator lifecycle, not to this cleanup.
     *
     * @param  array{face_id: string, external_image_id: string, image_id: string}  $face
     * @param  Collection<int, User>  $users
     */
    private function orphanReason(array $face, Collection $users): ?string
    {
        $externalImageId = $face['external_image_id'];

        if (! ctype_digit($externalImageId)) {
            return 'Sin colaborador asociado';
        }

        $user = $users->get((int) $externalImageId);

        if (! $user) {
            return 'El colaborador ya no existe';
        }

        if ($user->trashed()) {
            return 'El colaborador fue eliminado';
        }

        return null;
    }

    /**
     * A collaborator with more than one face only needs the one referenced by
     * their active enrollment. Without an active reference nothing is removed:
     * there is no safe way to tell which face is the current one.
     *
     * @param  array{face_id: string, external_image_id: string, image_id: string}  $face
     * @param  array<int, array<int, string>>  $activeReferences  active face ids per user
     * @param  Collection<string, int>  $facesPerCollaborator
     */
    private function duplicateReason(array $face, array $activeReferences, Collection $facesPerCollaborator): ?string
    {
        if (! $this->option('include-duplicates')) {
            return null;
        }

        if (($facesPerCollaborator[$face['external_image_id']] ?? 0) < 2) {
            return null;
        }

        $active = $activeReferences[(int) $face['external_image_id']] ?? [];

        if ($active === [] || in_array($face['face_id'], $active, true)) {
            return null;
        }

        return 'Rostro duplicado (se conserva el de la referencia activa)';
    }

    /**
     * Active face ids of every collaborator in the collection being reviewed.
     *
     * @return array<int, array<int, string>>
     */
    private function activeReferences(string $collection): array
    {
        return FaceEnrollment::query()
            ->active()
            ->where('collection_id', $collection)
            ->get(['user_id', 'face_id'])
            ->groupBy(fn (FaceEnrollment $enrollment) => $enrollment->user_id)
            ->map(fn (Collection $enrollments) => $enrollments->pluck('face_id')->all())
            ->all();
    }

    /**
     * @param  array<int, array{face_id: string, external_image_id: string, image_id: string}>  $faces
     * @return Collection<int, User>
     */
    private function usersByExternalId(array $faces): Collection
    {
        $userIds = collect($faces)
            ->pluck('external_image_id')
            ->filter(fn (string $externalId) => ctype_digit($externalId))
            ->map(fn (string $externalId) => (int) $externalId)
            ->unique()
            ->values();

        return User::query()
            ->withTrashed()
            ->whereIn('id', $userIds)
            ->get()
            ->keyBy(fn (User $user) => $user->getKey());
    }

    /**
     * The provider client runs inside this PHP process, so a broken CA bundle
     * in php.ini (curl.cainfo / openssl.cafile) surfaces as an opaque cURL 77.
     * Point the operator to the real cause instead of leaving them with it.
     */
    private function reportConnectionHint(string $message): void
    {
        $message = mb_strtolower($message);

        $failing = str_contains($message, 'curl error 77')
            || str_contains($message, 'ssl certificate problem')
            || str_contains($message, 'certificate verify failed')
            || str_contains($message, 'unable to get local issuer certificate');

        if (! $failing) {
            return;
        }

        $cainfo = (string) ini_get('curl.cainfo');
        $cafile = (string) ini_get('openssl.cafile');

        $this->newLine();
        $this->warn('Este PHP no puede validar los certificados HTTPS de AWS.');
        $this->line('   Archivo configurado en php.ini:');

        foreach (array_filter(['curl.cainfo' => $cainfo, 'openssl.cafile' => $cafile]) as $key => $path) {
            $this->line(sprintf('   · %s = %s (%s)', $key, $path, is_file($path) ? 'existe' : 'no existe'));
        }

        $this->line('   Descarga el bundle de CA desde https://curl.se/ca/cacert.pem y guárdalo en esa ruta,');
        $this->line('   o actualiza php.ini para apuntar a un cacert.pem válido.');
    }

    /**
     * @param  Collection<int, User>  $users
     */
    private function collaboratorLabel(string $externalImageId, Collection $users): string
    {
        $user = ctype_digit($externalImageId) ? $users->get((int) $externalImageId) : null;

        if (! $user) {
            return $externalImageId === '' ? '—' : $externalImageId;
        }

        return $user->name.' (#'.$user->getKey().')';
    }
}
