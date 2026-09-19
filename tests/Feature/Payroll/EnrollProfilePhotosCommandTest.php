<?php

namespace Tests\Feature\Payroll;

use App\Models\FaceEnrollment;
use App\Models\PayrollProfile;
use App\Models\PayrollSetting;
use App\Models\User;
use App\Services\Payroll\FaceRecognition\FaceRecognitionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Mockery\MockInterface;
use Tests\TestCase;

class EnrollProfilePhotosCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
    }

    private function collaboratorWithPhoto(): User
    {
        $user = User::factory()->create(['name' => 'Colaborador Foto', 'is_active' => true]);

        PayrollProfile::create([
            'user_id' => $user->id,
            'employee_number' => 'EMP-0600',
            'is_attendance_subject' => true,
        ]);

        $user->updateProfilePhoto(UploadedFile::fake()->image('rostro.jpg', 400, 400));

        return $user;
    }

    public function test_it_enrolls_the_profile_photos_of_collaborators(): void
    {
        PayrollSetting::current()->update(['face_recognition_enabled' => true]);

        $user = $this->collaboratorWithPhoto();

        $this->mock(FaceRecognitionService::class, function (MockInterface $mock) {
            $mock->shouldReceive('isConfigured')->andReturn(true);
            $mock->shouldReceive('collectionId')->andReturn('empleados_construmax');
            $mock->shouldReceive('indexFace')->once()->andReturn(['face_id' => 'command-face', 'quality' => 95.0]);
        });

        $this->artisan('payroll:enroll-profile-photos')
            ->expectsOutputToContain('Rostros registrados: 1')
            ->assertExitCode(0);

        $this->assertDatabaseHas('face_enrollments', [
            'user_id' => $user->id,
            'face_id' => 'command-face',
            'external_image_id' => (string) $user->id,
            'status' => FaceEnrollment::STATUS_ACTIVE,
        ]);
    }

    public function test_it_skips_collaborators_with_an_active_face_unless_forced(): void
    {
        PayrollSetting::current()->update(['face_recognition_enabled' => true]);

        $user = $this->collaboratorWithPhoto();

        FaceEnrollment::create([
            'user_id' => $user->id,
            'collection_id' => 'empleados_construmax',
            'face_id' => 'existing-face',
            'external_image_id' => (string) $user->id,
            'status' => FaceEnrollment::STATUS_ACTIVE,
            'enrolled_at' => now(),
        ]);

        $this->mock(FaceRecognitionService::class, function (MockInterface $mock) {
            $mock->shouldReceive('isConfigured')->andReturn(true);
            $mock->shouldNotReceive('indexFace');
        });

        $this->artisan('payroll:enroll-profile-photos')
            ->expectsOutputToContain('Omitidos: 1')
            ->assertExitCode(0);

        $this->assertDatabaseCount('face_enrollments', 1);
    }

    public function test_it_fails_with_a_clear_message_when_the_recognition_is_disabled(): void
    {
        PayrollSetting::current()->update(['face_recognition_enabled' => false]);

        $this->mock(FaceRecognitionService::class, function (MockInterface $mock) {
            $mock->shouldReceive('isConfigured')->andReturn(true);
            $mock->shouldNotReceive('indexFace');
        });

        $this->artisan('payroll:enroll-profile-photos')
            ->expectsOutputToContain('desactivado')
            ->assertExitCode(1);
    }
}
