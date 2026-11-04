<?php

namespace Tests\Feature\Payroll;

use App\Models\FaceEnrollment;
use App\Models\PayrollProfile;
use App\Models\User;
use App\Services\Payroll\FaceRecognition\FaceRecognitionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\MockInterface;
use Tests\TestCase;

class OrphanFacesCommandTest extends TestCase
{
    use RefreshDatabase;

    private function face(string $faceId, User|int|null $user): array
    {
        return [
            'face_id' => $faceId,
            'external_image_id' => match (true) {
                $user instanceof User => (string) $user->id,
                is_int($user) => (string) $user,
                default => 'sin-colaborador',
            },
            'image_id' => 'image-'.$faceId,
        ];
    }

    private function enrollment(User $user, string $faceId): FaceEnrollment
    {
        return FaceEnrollment::create([
            'user_id' => $user->id,
            'collection_id' => 'empleados_construmax',
            'face_id' => $faceId,
            'external_image_id' => (string) $user->id,
            'status' => FaceEnrollment::STATUS_ACTIVE,
            'enrolled_at' => now(),
        ]);
    }

    private function activeCollaborator(): User
    {
        $user = User::factory()->create(['name' => 'Colaborador Activo', 'is_active' => true]);

        PayrollProfile::create([
            'user_id' => $user->id,
            'employee_number' => 'EMP-0700',
            'is_attendance_subject' => true,
        ]);

        return $user;
    }

    public function test_it_lists_only_the_faces_of_collaborators_that_no_longer_exist(): void
    {
        $active = $this->activeCollaborator();
        $this->enrollment($active, 'face-active');

        $dismissed = User::factory()->create(['name' => 'Colaborador Baja', 'is_active' => false]);
        PayrollProfile::create([
            'user_id' => $dismissed->id,
            'employee_number' => 'EMP-0701',
            'is_attendance_subject' => true,
            'termination_date' => '2026-09-15',
        ]);
        $dismissedEnrollment = $this->enrollment($dismissed, 'face-dismissed');

        $deleted = User::factory()->create(['name' => 'Colaborador Eliminado', 'is_active' => true]);
        $this->enrollment($deleted, 'face-deleted');
        $deleted->delete();

        $this->mock(FaceRecognitionService::class, function (MockInterface $mock) use ($active, $dismissed, $deleted) {
            $mock->shouldReceive('isConfigured')->andReturn(true);
            $mock->shouldReceive('collectionId')->andReturn('empleados_construmax');
            $mock->shouldReceive('listFaces')->once()->andReturn([
                $this->face('face-active', $active),
                $this->face('face-dismissed', $dismissed),
                $this->face('face-deleted', $deleted),
                // A face whose collaborator was deleted long ago.
                $this->face('face-missing', 9999),
            ]);
            $mock->shouldNotReceive('removeFaces');
        });

        $this->artisan('rekognition:purge-orphan-faces')
            ->expectsOutputToContain('Se conservan 2 rostros')
            ->expectsOutputToContain('2 rostros encontrados')
            ->assertExitCode(0);

        $this->assertDatabaseHas('face_enrollments', [
            'id' => $dismissedEnrollment->id,
            'status' => FaceEnrollment::STATUS_ACTIVE,
        ]);
    }

    public function test_it_keeps_the_face_of_a_collaborator_without_attendance(): void
    {
        // Not registering attendance does not mean the collaborator is gone.
        $user = User::factory()->create(['name' => 'Sin Asistencia', 'is_active' => true]);
        PayrollProfile::create([
            'user_id' => $user->id,
            'employee_number' => 'EMP-0702',
            'is_attendance_subject' => false,
        ]);

        $this->mock(FaceRecognitionService::class, function (MockInterface $mock) use ($user) {
            $mock->shouldReceive('isConfigured')->andReturn(true);
            $mock->shouldReceive('collectionId')->andReturn('empleados_construmax');
            $mock->shouldReceive('listFaces')->once()->andReturn([
                $this->face('face-no-attendance', $user),
            ]);
            $mock->shouldNotReceive('removeFaces');
        });

        $this->artisan('rekognition:purge-orphan-faces', ['--delete' => true])
            ->expectsOutputToContain('Se conservan 1 rostros')
            ->expectsOutputToContain('No se encontraron rostros a eliminar')
            ->assertExitCode(0);
    }

    public function test_it_keeps_the_face_of_a_dismissed_collaborator(): void
    {
        $dismissed = User::factory()->create(['name' => 'Colaborador Baja', 'is_active' => false]);
        PayrollProfile::create([
            'user_id' => $dismissed->id,
            'employee_number' => 'EMP-0703',
            'is_attendance_subject' => true,
            'termination_date' => '2026-09-15',
        ]);
        $enrollment = $this->enrollment($dismissed, 'face-dismissed');

        $this->mock(FaceRecognitionService::class, function (MockInterface $mock) use ($dismissed) {
            $mock->shouldReceive('isConfigured')->andReturn(true);
            $mock->shouldReceive('collectionId')->andReturn('empleados_construmax');
            $mock->shouldReceive('listFaces')->once()->andReturn([
                $this->face('face-dismissed', $dismissed),
            ]);
            $mock->shouldNotReceive('removeFaces');
        });

        $this->artisan('rekognition:purge-orphan-faces', ['--delete' => true])
            ->assertExitCode(0);

        $this->assertDatabaseHas('face_enrollments', [
            'id' => $enrollment->id,
            'status' => FaceEnrollment::STATUS_ACTIVE,
        ]);
    }

    public function test_it_deletes_only_the_faces_of_nonexistent_collaborators(): void
    {
        $active = $this->activeCollaborator();
        $activeEnrollment = $this->enrollment($active, 'face-active');

        $dismissed = User::factory()->create(['name' => 'Colaborador Baja', 'is_active' => false]);
        $dismissedEnrollment = $this->enrollment($dismissed, 'face-dismissed');

        $deleted = User::factory()->create(['name' => 'Colaborador Eliminado', 'is_active' => true]);
        $deletedEnrollment = $this->enrollment($deleted, 'face-deleted');
        $deleted->delete();

        $this->mock(FaceRecognitionService::class, function (MockInterface $mock) use ($active, $dismissed, $deleted) {
            $mock->shouldReceive('isConfigured')->andReturn(true);
            $mock->shouldReceive('collectionId')->andReturn('empleados_construmax');
            $mock->shouldReceive('listFaces')->once()->andReturn([
                $this->face('face-active', $active),
                $this->face('face-dismissed', $dismissed),
                $this->face('face-deleted', $deleted),
                $this->face('face-missing', 9999),
            ]);
            $mock->shouldReceive('removeFaces')
                ->once()
                ->with(['face-deleted', 'face-missing'])
                ->andReturn(['face-deleted', 'face-missing']);
        });

        $this->artisan('rekognition:purge-orphan-faces', ['--delete' => true])
            ->expectsOutputToContain('2 rostros eliminados')
            ->assertExitCode(0);

        $this->assertDatabaseHas('face_enrollments', [
            'id' => $deletedEnrollment->id,
            'status' => FaceEnrollment::STATUS_REMOVED,
        ]);
        $this->assertDatabaseHas('face_enrollments', [
            'id' => $activeEnrollment->id,
            'status' => FaceEnrollment::STATUS_ACTIVE,
        ]);
        $this->assertDatabaseHas('face_enrollments', [
            'id' => $dismissedEnrollment->id,
            'status' => FaceEnrollment::STATUS_ACTIVE,
        ]);
    }

    public function test_it_keeps_a_duplicate_face_unless_duplicates_are_included(): void
    {
        $user = $this->activeCollaborator();
        $this->enrollment($user, 'face-active');

        $this->mock(FaceRecognitionService::class, function (MockInterface $mock) use ($user) {
            $mock->shouldReceive('isConfigured')->andReturn(true);
            $mock->shouldReceive('collectionId')->andReturn('empleados_construmax');
            $mock->shouldReceive('listFaces')->once()->andReturn([
                $this->face('face-active', $user),
                $this->face('face-old', $user),
            ]);
            $mock->shouldNotReceive('removeFaces');
        });

        $this->artisan('rekognition:purge-orphan-faces', ['--delete' => true])
            ->expectsOutputToContain('No se encontraron rostros a eliminar')
            ->assertExitCode(0);
    }

    public function test_it_deletes_the_duplicate_faces_keeping_the_active_reference(): void
    {
        $user = $this->activeCollaborator();
        $activeEnrollment = $this->enrollment($user, 'face-active');
        // A leftover reference from a previous portrait, no longer active.
        FaceEnrollment::create([
            'user_id' => $user->id,
            'collection_id' => 'empleados_construmax',
            'face_id' => 'face-old',
            'external_image_id' => (string) $user->id,
            'status' => FaceEnrollment::STATUS_REMOVED,
            'enrolled_at' => now()->subMonth(),
        ]);

        $this->mock(FaceRecognitionService::class, function (MockInterface $mock) use ($user) {
            $mock->shouldReceive('isConfigured')->andReturn(true);
            $mock->shouldReceive('collectionId')->andReturn('empleados_construmax');
            $mock->shouldReceive('listFaces')->once()->andReturn([
                $this->face('face-active', $user),
                $this->face('face-old', $user),
            ]);
            $mock->shouldReceive('removeFaces')->once()->with(['face-old'])->andReturn(['face-old']);
        });

        $this->artisan('rekognition:purge-orphan-faces', ['--delete' => true, '--include-duplicates' => true])
            ->expectsOutputToContain('Rostro duplicado')
            ->expectsOutputToContain('1 rostros eliminados')
            ->assertExitCode(0);

        $this->assertDatabaseHas('face_enrollments', [
            'id' => $activeEnrollment->id,
            'status' => FaceEnrollment::STATUS_ACTIVE,
        ]);
    }

    public function test_it_keeps_every_face_of_a_duplicate_collaborator_without_an_active_reference(): void
    {
        $user = $this->activeCollaborator();
        $this->enrollment($user, 'face-a');

        // Neither face is the active reference anymore: there is no safe way
        // to tell which one is current, so both stay.
        FaceEnrollment::query()->update(['status' => FaceEnrollment::STATUS_REMOVED]);

        $this->mock(FaceRecognitionService::class, function (MockInterface $mock) use ($user) {
            $mock->shouldReceive('isConfigured')->andReturn(true);
            $mock->shouldReceive('collectionId')->andReturn('empleados_construmax');
            $mock->shouldReceive('listFaces')->once()->andReturn([
                $this->face('face-a', $user),
                $this->face('face-b', $user),
            ]);
            $mock->shouldNotReceive('removeFaces');
        });

        $this->artisan('rekognition:purge-orphan-faces', ['--delete' => true, '--include-duplicates' => true])
            ->expectsOutputToContain('No se encontraron rostros a eliminar')
            ->assertExitCode(0);
    }

    public function test_it_reports_when_every_face_belongs_to_an_existing_collaborator(): void
    {
        $active = $this->activeCollaborator();
        $this->enrollment($active, 'face-active');

        $this->mock(FaceRecognitionService::class, function (MockInterface $mock) use ($active) {
            $mock->shouldReceive('isConfigured')->andReturn(true);
            $mock->shouldReceive('collectionId')->andReturn('empleados_construmax');
            $mock->shouldReceive('listFaces')->once()->andReturn([
                $this->face('face-active', $active),
            ]);
            $mock->shouldNotReceive('removeFaces');
        });

        $this->artisan('rekognition:purge-orphan-faces', ['--delete' => true])
            ->expectsOutputToContain('No se encontraron rostros a eliminar')
            ->assertExitCode(0);
    }

    public function test_it_explains_a_broken_ca_bundle_instead_of_only_the_curl_error(): void
    {
        $this->mock(FaceRecognitionService::class, function (MockInterface $mock) {
            $mock->shouldReceive('isConfigured')->andReturn(true);
            $mock->shouldReceive('collectionId')->andReturn('empleados_construmax');
            $mock->shouldReceive('listFaces')->once()->andThrow(new \RuntimeException(
                'Error executing "ListFaces": cURL error 77: error adding trust anchors from file: C:\\laragon\\etc\\ssl\\cacert.pem'
            ));
        });

        $this->artisan('rekognition:purge-orphan-faces')
            ->expectsOutputToContain('curl.cainfo')
            ->expectsOutputToContain('https://curl.se/ca/cacert.pem')
            ->assertExitCode(1);
    }

    public function test_it_fails_when_the_provider_is_not_configured(): void
    {
        $this->mock(FaceRecognitionService::class, function (MockInterface $mock) {
            $mock->shouldReceive('isConfigured')->andReturn(false);
            $mock->shouldNotReceive('listFaces');
        });

        $this->artisan('rekognition:purge-orphan-faces')
            ->expectsOutputToContain('no está configurado')
            ->assertExitCode(1);
    }
}
