<?php

namespace Tests\Feature\Payroll;

use App\Models\FaceEnrollment;
use App\Models\PayrollProfile;
use App\Models\PayrollSetting;
use App\Models\Technician;
use App\Models\User;
use App\Services\Payroll\FaceRecognition\FaceRecognitionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Mockery\MockInterface;
use RuntimeException;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ProfilePhotoEnrollmentTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $employee;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');

        Role::create(['name' => 'Employee', 'guard_name' => 'web']);

        Permission::create([
            'name' => 'payroll.profiles.manage',
            'guard_name' => 'web',
            'category' => 'Nómina',
            'description' => 'Manage payroll profiles',
        ]);

        $this->admin = User::factory()->create(['is_active' => true]);
        $this->admin->givePermissionTo('payroll.profiles.manage');

        $this->employee = User::factory()->create([
            'name' => 'Empleado Foto',
            'email' => 'empleado.foto@test.com',
            'is_active' => true,
        ]);

        PayrollProfile::create([
            'user_id' => $this->employee->id,
            'employee_number' => 'EMP-0500',
            'is_attendance_subject' => true,
        ]);
    }

    private function storePayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Nuevo Colaborador',
            'email' => 'nuevo.foto@test.com',
            'password' => 'Password123!',
            'roles' => ['Employee'],
            'department' => 'Obras',
            'position' => 'Ayudante',
            'phone' => '3331234567',
            'is_attendance_subject' => true,
        ], $overrides);
    }

    private function updatePayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Empleado Foto',
            'email' => 'empleado.foto@test.com',
            'roles' => ['Employee'],
            'department' => 'Obras',
            'position' => 'Ayudante',
            'phone' => '3331234567',
        ], $overrides);
    }

    private function activeEnrollment(string $faceId = 'old-face'): FaceEnrollment
    {
        return FaceEnrollment::create([
            'user_id' => $this->employee->id,
            'collection_id' => 'construmax-attendance',
            'face_id' => $faceId,
            'external_image_id' => (string) $this->employee->id,
            'status' => FaceEnrollment::STATUS_ACTIVE,
            'enrolled_at' => now(),
        ]);
    }

    public function test_store_saves_the_profile_photo_without_facial_enrollment(): void
    {
        $this->mock(FaceRecognitionService::class, function (MockInterface $mock) {
            $mock->shouldReceive('isConfigured')->andReturn(false);
        });

        $this->actingAs($this->admin)
            ->post(route('users.store'), $this->storePayload([
                'photo' => UploadedFile::fake()->image('rostro.jpg', 400, 400),
            ]))
            ->assertRedirect(route('users.index'))
            ->assertSessionHas('success', 'Usuario creado y roles asignados correctamente.');

        $user = User::where('email', 'nuevo.foto@test.com')->first();

        $this->assertNotNull($user);
        $this->assertNotNull($user->profile_photo_path);
        $this->assertDatabaseCount('face_enrollments', 0);
    }

    public function test_store_enrolls_the_profile_photo_when_recognition_is_active(): void
    {
        PayrollSetting::current()->update(['face_recognition_enabled' => true]);

        $this->mock(FaceRecognitionService::class, function (MockInterface $mock) {
            $mock->shouldReceive('isConfigured')->andReturn(true);
            $mock->shouldReceive('collectionId')->andReturn('construmax-attendance');
            $mock->shouldReceive('indexFace')->once()->andReturn(['face_id' => 'profile-face', 'quality' => 96.5]);
        });

        $this->actingAs($this->admin)
            ->post(route('users.store'), $this->storePayload([
                'photo' => UploadedFile::fake()->image('rostro.jpg', 400, 400),
            ]))
            ->assertRedirect(route('users.index'))
            ->assertSessionHas('success', fn (string $message) => str_contains($message, 'referencia facial'));

        $user = User::where('email', 'nuevo.foto@test.com')->first();

        $this->assertNotNull($user->profile_photo_path);
        $this->assertDatabaseHas('face_enrollments', [
            'user_id' => $user->id,
            'face_id' => 'profile-face',
            'external_image_id' => (string) $user->id,
            'status' => FaceEnrollment::STATUS_ACTIVE,
            'enrolled_by' => $this->admin->id,
        ]);
    }

    public function test_update_replaces_the_previous_face_reference_with_the_new_photo(): void
    {
        $this->activeEnrollment('old-face');

        PayrollSetting::current()->update(['face_recognition_enabled' => true]);

        $this->mock(FaceRecognitionService::class, function (MockInterface $mock) {
            $mock->shouldReceive('isConfigured')->andReturn(true);
            $mock->shouldReceive('collectionId')->andReturn('construmax-attendance');
            $mock->shouldReceive('indexFace')->once()->andReturn(['face_id' => 'new-face', 'quality' => 98.5]);
            $mock->shouldReceive('removeFace')->once()->with('old-face');
        });

        $this->actingAs($this->admin)
            ->put(route('users.update', $this->employee->id), $this->updatePayload([
                'photo' => UploadedFile::fake()->image('nuevo-rostro.jpg', 400, 400),
            ]))
            ->assertRedirect(route('users.index'))
            ->assertSessionHas('success', fn (string $message) => str_contains($message, 'referencia facial'));

        $this->assertDatabaseHas('face_enrollments', [
            'face_id' => 'old-face',
            'status' => FaceEnrollment::STATUS_REMOVED,
        ]);
        $this->assertDatabaseHas('face_enrollments', [
            'face_id' => 'new-face',
            'status' => FaceEnrollment::STATUS_ACTIVE,
        ]);
    }

    public function test_update_keeps_the_previous_reference_when_the_new_photo_has_no_face(): void
    {
        $this->activeEnrollment('old-face');

        PayrollSetting::current()->update(['face_recognition_enabled' => true]);

        $this->mock(FaceRecognitionService::class, function (MockInterface $mock) {
            $mock->shouldReceive('isConfigured')->andReturn(true);
            $mock->shouldReceive('indexFace')->once()->andThrow(new RuntimeException('No face detected'));
            $mock->shouldNotReceive('removeFace');
        });

        $this->actingAs($this->admin)
            ->put(route('users.update', $this->employee->id), $this->updatePayload([
                'photo' => UploadedFile::fake()->image('sin-rostro.jpg', 400, 400),
            ]))
            ->assertRedirect(route('users.index'))
            ->assertSessionHas('success', fn (string $message) => str_contains($message, 'No se pudo detectar el rostro'));

        $this->assertDatabaseHas('face_enrollments', [
            'face_id' => 'old-face',
            'status' => FaceEnrollment::STATUS_ACTIVE,
        ]);
        $this->assertDatabaseMissing('face_enrollments', [
            'status' => FaceEnrollment::STATUS_REMOVED,
        ]);
    }

    public function test_update_without_photo_does_not_touch_the_facial_references(): void
    {
        $this->activeEnrollment('old-face');

        $this->mock(FaceRecognitionService::class, function (MockInterface $mock) {
            $mock->shouldNotReceive('indexFace');
            $mock->shouldNotReceive('removeFace');
        });

        $this->actingAs($this->admin)
            ->put(route('users.update', $this->employee->id), $this->updatePayload([
                'name' => 'Empleado Foto Actualizado',
            ]))
            ->assertRedirect(route('users.index'))
            ->assertSessionHas('success', 'Usuario actualizado exitosamente.');

        $this->assertDatabaseHas('face_enrollments', [
            'face_id' => 'old-face',
            'status' => FaceEnrollment::STATUS_ACTIVE,
        ]);
        $this->assertSame('Empleado Foto Actualizado', $this->employee->fresh()->name);
    }

    public function test_technician_store_enrolls_the_profile_photo_when_recognition_is_active(): void
    {
        PayrollSetting::current()->update(['face_recognition_enabled' => true]);

        $this->mock(FaceRecognitionService::class, function (MockInterface $mock) {
            $mock->shouldReceive('isConfigured')->andReturn(true);
            $mock->shouldReceive('collectionId')->andReturn('construmax-attendance');
            $mock->shouldReceive('indexFace')->once()->andReturn(['face_id' => 'tech-face', 'quality' => 94.0]);
        });

        $this->actingAs($this->admin)
            ->post(route('technicians.store'), [
                'name' => 'Técnico Con Foto',
                'email' => 'tecnico.foto@test.com',
                'phone' => '3311112233',
                'is_attendance_subject' => true,
                'photo' => UploadedFile::fake()->image('tecnico.jpg', 400, 400),
            ])
            ->assertRedirect(route('technicians.index'));

        $technician = Technician::whereHas('user', fn ($query) => $query->where('email', 'tecnico.foto@test.com'))->first();

        $this->assertNotNull($technician);
        $this->assertNotNull($technician->user->profile_photo_path);
        $this->assertDatabaseHas('face_enrollments', [
            'user_id' => $technician->user_id,
            'face_id' => 'tech-face',
            'external_image_id' => (string) $technician->user_id,
            'status' => FaceEnrollment::STATUS_ACTIVE,
            'enrolled_by' => $this->admin->id,
        ]);
    }

    public function test_store_rejects_a_file_that_is_not_a_valid_image(): void
    {
        $this->actingAs($this->admin)
            ->post(route('users.store'), $this->storePayload([
                'photo' => UploadedFile::fake()->create('documento.pdf', 50, 'application/pdf'),
            ]))
            ->assertSessionHasErrors('photo');

        $this->assertDatabaseMissing('users', ['email' => 'nuevo.foto@test.com']);
    }
}
