<?php

namespace Tests\Feature\Payroll;

use App\Models\AttendanceDevice;
use App\Models\AttendanceLog;
use App\Models\FaceEnrollment;
use App\Models\PayrollProfile;
use App\Models\PayrollSetting;
use App\Models\User;
use App\Services\Payroll\FaceRecognition\FaceRecognitionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\MockInterface;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class FaceRecognitionTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $employee;

    private AttendanceDevice $device;

    private string $plainToken = 'face-kiosk-token';

    protected function setUp(): void
    {
        parent::setUp();

        Permission::create([
            'name' => 'payroll.faces.manage',
            'guard_name' => 'web',
            'category' => 'Nómina',
            'description' => 'Manage facial enrollments',
        ]);

        $this->admin = User::factory()->create(['is_active' => true]);
        $this->admin->givePermissionTo('payroll.faces.manage');

        $this->employee = User::factory()->create(['name' => 'Ana López', 'is_active' => true]);

        PayrollProfile::create([
            'user_id' => $this->employee->id,
            'employee_number' => 'EMP-0200',
            'is_attendance_subject' => true,
        ]);

        $this->device = AttendanceDevice::create([
            'name' => 'Tablet recepción',
            'token_hash' => hash('sha256', $this->plainToken),
            'registered_at' => now(),
            'is_active' => true,
        ]);
    }

    private function deviceHeaders(): array
    {
        return ['X-Attendance-Device' => $this->plainToken];
    }

    private function enableFaceRecognition(): void
    {
        PayrollSetting::current()->update(['face_recognition_enabled' => true]);
    }

    public function test_kiosk_face_punch_registers_the_log_with_face_identification(): void
    {
        $this->enableFaceRecognition();

        $this->mock(FaceRecognitionService::class, function (MockInterface $mock) {
            $mock->shouldReceive('isConfigured')->andReturn(true);
            $mock->shouldReceive('search')->once()->andReturn([
                'external_image_id' => (string) $this->employee->id,
                'face_id' => 'aws-face-1',
                'similarity' => 98.42,
            ]);
        });

        $this->postJson(route('attendance.kiosk.face-punch'), [
            'type' => AttendanceLog::TYPE_CHECK_IN,
            'photo' => 'data:image/jpeg;base64,/9j/AAAA',
        ], $this->deviceHeaders())
            ->assertOk()
            ->assertJsonPath('user_name', 'Ana López')
            ->assertJsonPath('employee_number', 'EMP-0200')
            ->assertJsonPath('type_label', 'Entrada')
            ->assertJsonPath('similarity', 98.42)
            ->assertJsonPath('suggested_next', AttendanceLog::TYPE_LUNCH_START);

        $this->assertDatabaseHas('attendance_logs', [
            'user_id' => $this->employee->id,
            'attendance_device_id' => $this->device->id,
            'type' => AttendanceLog::TYPE_CHECK_IN,
            'source' => AttendanceLog::SOURCE_KIOSK,
            'identifier_method' => AttendanceLog::IDENTIFIER_FACE,
        ]);
    }

    public function test_kiosk_face_punch_is_rejected_when_recognition_is_disabled(): void
    {
        PayrollSetting::current()->update(['face_recognition_enabled' => false]);

        $this->mock(FaceRecognitionService::class, function (MockInterface $mock) {
            $mock->shouldReceive('isConfigured')->andReturn(true);
            $mock->shouldNotReceive('search');
        });

        $this->postJson(route('attendance.kiosk.face-punch'), [
            'type' => AttendanceLog::TYPE_CHECK_IN,
            'photo' => 'data:image/jpeg;base64,/9j/AAAA',
        ], $this->deviceHeaders())
            ->assertStatus(422)
            ->assertJsonValidationErrors('face');

        $this->assertDatabaseCount('attendance_logs', 0);
    }

    public function test_kiosk_face_punch_is_rejected_when_the_provider_is_not_configured(): void
    {
        $this->enableFaceRecognition();

        $this->mock(FaceRecognitionService::class, function (MockInterface $mock) {
            $mock->shouldReceive('isConfigured')->andReturn(false);
            $mock->shouldNotReceive('search');
        });

        $this->postJson(route('attendance.kiosk.face-punch'), [
            'type' => AttendanceLog::TYPE_CHECK_IN,
            'photo' => 'data:image/jpeg;base64,/9j/AAAA',
        ], $this->deviceHeaders())
            ->assertStatus(422)
            ->assertJsonValidationErrors('face');
    }

    public function test_kiosk_face_punch_is_rejected_when_the_face_is_not_recognized(): void
    {
        $this->enableFaceRecognition();

        $this->mock(FaceRecognitionService::class, function (MockInterface $mock) {
            $mock->shouldReceive('isConfigured')->andReturn(true);
            $mock->shouldReceive('search')->once()->andReturn(null);
        });

        $this->postJson(route('attendance.kiosk.face-punch'), [
            'type' => AttendanceLog::TYPE_CHECK_IN,
            'photo' => 'data:image/jpeg;base64,/9j/AAAA',
        ], $this->deviceHeaders())
            ->assertStatus(422)
            ->assertJsonValidationErrors('face');

        $this->assertDatabaseCount('attendance_logs', 0);
    }

    public function test_kiosk_face_punch_requires_the_device_token(): void
    {
        $this->postJson(route('attendance.kiosk.face-punch'), [
            'type' => AttendanceLog::TYPE_CHECK_IN,
            'photo' => 'data:image/jpeg;base64,/9j/AAAA',
        ])->assertForbidden();
    }

    public function test_admin_can_enroll_the_faces_of_a_collaborator(): void
    {
        $this->mock(FaceRecognitionService::class, function (MockInterface $mock) {
            $mock->shouldReceive('isConfigured')->andReturn(true);
            $mock->shouldReceive('collectionId')->andReturn('construmax-attendance');
            $mock->shouldReceive('removeFace')->andReturnNull();
            $mock->shouldReceive('indexFace')->twice()->andReturn(
                ['face_id' => 'aws-face-1', 'quality' => 99.1],
                ['face_id' => 'aws-face-2', 'quality' => 97.3],
            );
        });

        $this->actingAs($this->admin)
            ->post(route('payroll.faces.store', $this->employee->id), [
                'photos' => [
                    'data:image/jpeg;base64,/9j/AAAA',
                    'data:image/jpeg;base64,/9j/BBBB',
                ],
            ])
            ->assertRedirect();

        $this->assertDatabaseCount('face_enrollments', 2);
        $this->assertDatabaseHas('face_enrollments', [
            'user_id' => $this->employee->id,
            'face_id' => 'aws-face-1',
            'external_image_id' => (string) $this->employee->id,
            'status' => FaceEnrollment::STATUS_ACTIVE,
            'enrolled_by' => $this->admin->id,
        ]);
    }

    public function test_enrollment_replaces_the_previous_active_faces(): void
    {
        FaceEnrollment::create([
            'user_id' => $this->employee->id,
            'collection_id' => 'construmax-attendance',
            'face_id' => 'old-face',
            'external_image_id' => (string) $this->employee->id,
            'status' => FaceEnrollment::STATUS_ACTIVE,
            'enrolled_at' => now(),
        ]);

        $this->mock(FaceRecognitionService::class, function (MockInterface $mock) {
            $mock->shouldReceive('isConfigured')->andReturn(true);
            $mock->shouldReceive('collectionId')->andReturn('construmax-attendance');
            $mock->shouldReceive('removeFace')->once()->with('old-face');
            $mock->shouldReceive('indexFace')->once()->andReturn(['face_id' => 'new-face', 'quality' => 98.0]);
        });

        $this->actingAs($this->admin)
            ->post(route('payroll.faces.store', $this->employee->id), [
                'photos' => ['data:image/jpeg;base64,/9j/CCCC'],
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('face_enrollments', [
            'face_id' => 'old-face',
            'status' => FaceEnrollment::STATUS_REMOVED,
        ]);
        $this->assertDatabaseHas('face_enrollments', [
            'face_id' => 'new-face',
            'status' => FaceEnrollment::STATUS_ACTIVE,
        ]);
    }

    public function test_enrollment_requires_the_manage_permission(): void
    {
        $user = User::factory()->create(['is_active' => true]);

        $this->actingAs($user)
            ->post(route('payroll.faces.store', $this->employee->id), [
                'photos' => ['data:image/jpeg;base64,/9j/AAAA'],
            ])
            ->assertForbidden();

        $this->assertDatabaseCount('face_enrollments', 0);
    }

    public function test_enrollment_fails_when_the_provider_is_not_configured(): void
    {
        $this->mock(FaceRecognitionService::class, function (MockInterface $mock) {
            $mock->shouldReceive('isConfigured')->andReturn(false);
            $mock->shouldNotReceive('indexFace');
        });

        $this->actingAs($this->admin)
            ->postJson(route('payroll.faces.store', $this->employee->id), [
                'photos' => ['data:image/jpeg;base64,/9j/AAAA'],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('photos');

        $this->assertDatabaseCount('face_enrollments', 0);
    }

    public function test_admin_can_remove_the_faces_of_a_collaborator(): void
    {
        FaceEnrollment::create([
            'user_id' => $this->employee->id,
            'collection_id' => 'construmax-attendance',
            'face_id' => 'aws-face-1',
            'external_image_id' => (string) $this->employee->id,
            'status' => FaceEnrollment::STATUS_ACTIVE,
            'enrolled_at' => now(),
        ]);

        FaceEnrollment::create([
            'user_id' => $this->employee->id,
            'collection_id' => 'construmax-attendance',
            'face_id' => 'aws-face-2',
            'external_image_id' => (string) $this->employee->id,
            'status' => FaceEnrollment::STATUS_ACTIVE,
            'enrolled_at' => now(),
        ]);

        $this->mock(FaceRecognitionService::class, function (MockInterface $mock) {
            $mock->shouldReceive('removeFace')->twice();
        });

        $this->actingAs($this->admin)
            ->delete(route('payroll.faces.destroy', $this->employee->id))
            ->assertRedirect();

        $this->assertDatabaseCount('face_enrollments', 2);
        $this->assertSame(
            0,
            FaceEnrollment::where('user_id', $this->employee->id)->active()->count()
        );
    }

    public function test_self_enrollment_requires_an_attendance_subject_profile(): void
    {
        $user = User::factory()->create(['is_active' => true]);

        $this->actingAs($user)
            ->postJson(route('payroll.my-attendance.faces.store'), [
                'photos' => ['data:image/jpeg;base64,/9j/AAAA'],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('photos');
    }

    public function test_self_enrollment_registers_the_own_face(): void
    {
        $this->mock(FaceRecognitionService::class, function (MockInterface $mock) {
            $mock->shouldReceive('isConfigured')->andReturn(true);
            $mock->shouldReceive('collectionId')->andReturn('construmax-attendance');
            $mock->shouldReceive('indexFace')->once()->andReturn(['face_id' => 'self-face', 'quality' => 96.5]);
        });

        $this->actingAs($this->employee)
            ->post(route('payroll.my-attendance.faces.store'), [
                'photos' => ['data:image/jpeg;base64,/9j/DDDD'],
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('face_enrollments', [
            'user_id' => $this->employee->id,
            'face_id' => 'self-face',
            'status' => FaceEnrollment::STATUS_ACTIVE,
            'enrolled_by' => $this->employee->id,
        ]);
    }

    public function test_faces_status_endpoint_reports_the_provider_configuration(): void
    {
        $this->mock(FaceRecognitionService::class, function (MockInterface $mock) {
            $mock->shouldReceive('isConfigured')->andReturn(true);
        });

        $this->actingAs($this->employee)
            ->getJson(route('payroll.my-attendance.faces.status'))
            ->assertOk()
            ->assertJsonPath('configured', true);
    }
}
