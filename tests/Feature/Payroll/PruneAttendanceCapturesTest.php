<?php

namespace Tests\Feature\Payroll;

use App\Models\AttendanceLog;
use App\Models\PayrollSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PruneAttendanceCapturesTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_prunes_captures_older_than_the_retention_window(): void
    {
        Storage::fake('public');

        PayrollSetting::current()->update(['attendance_capture_retention_months' => 12]);

        $user = User::factory()->create(['is_active' => true]);

        $old = AttendanceLog::create([
            'user_id' => $user->id,
            'type' => AttendanceLog::TYPE_CHECK_IN,
            'punched_at' => now()->subMonths(14),
            'source' => AttendanceLog::SOURCE_KIOSK,
            'identifier_method' => AttendanceLog::IDENTIFIER_PIN,
        ]);
        $old->addMediaFromString('old-capture')->usingFileName('old.jpg')->toMediaCollection('capture');

        $recent = AttendanceLog::create([
            'user_id' => $user->id,
            'type' => AttendanceLog::TYPE_LUNCH_START,
            'punched_at' => now()->subMonths(2),
            'source' => AttendanceLog::SOURCE_KIOSK,
            'identifier_method' => AttendanceLog::IDENTIFIER_PIN,
        ]);
        $recent->addMediaFromString('recent-capture')->usingFileName('recent.jpg')->toMediaCollection('capture');

        $this->artisan('payroll:prune-captures')->assertSuccessful();

        $this->assertFalse($old->fresh()->hasMedia('capture'));
        $this->assertTrue($recent->fresh()->hasMedia('capture'));
    }

    public function test_it_does_nothing_when_the_retention_window_is_disabled(): void
    {
        Storage::fake('public');

        PayrollSetting::current()->update(['attendance_capture_retention_months' => 0]);

        $user = User::factory()->create(['is_active' => true]);

        $log = AttendanceLog::create([
            'user_id' => $user->id,
            'type' => AttendanceLog::TYPE_CHECK_IN,
            'punched_at' => now()->subYears(5),
            'source' => AttendanceLog::SOURCE_KIOSK,
            'identifier_method' => AttendanceLog::IDENTIFIER_PIN,
        ]);
        $log->addMediaFromString('capture')->usingFileName('capture.jpg')->toMediaCollection('capture');

        $this->artisan('payroll:prune-captures')->assertSuccessful();

        $this->assertTrue($log->fresh()->hasMedia('capture'));
    }
}
