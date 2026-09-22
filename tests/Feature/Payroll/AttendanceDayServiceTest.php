<?php

namespace Tests\Feature\Payroll;

use App\Models\AttendanceDayOverride;
use App\Models\AttendanceLog;
use App\Models\Incident;
use App\Models\Shift;
use App\Models\ShiftAssignment;
use App\Models\User;
use App\Services\Payroll\AttendanceDayService;
use App\Services\Payroll\AttendanceDaySummary;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class AttendanceDayServiceTest extends TestCase
{
    use RefreshDatabase;

    private AttendanceDayService $service;

    private User $user;

    private Shift $shift;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(AttendanceDayService::class);
        $this->user = User::factory()->create(['is_active' => true]);

        $this->shift = Shift::create([
            'name' => 'Matutino',
            'type' => 'fixed',
            'start_time' => '08:00',
            'end_time' => '17:00',
            'meal_minutes' => 60,
            'is_meal_paid' => false,
            'days' => [1, 2, 3, 4, 5],
            'is_active' => true,
        ]);

        ShiftAssignment::create([
            'user_id' => $this->user->id,
            'type' => ShiftAssignment::TYPE_FIXED,
            'shift_id' => $this->shift->id,
            'start_date' => '2026-09-01',
            'is_active' => true,
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function punch(string $type, string $datetime): AttendanceLog
    {
        return AttendanceLog::create([
            'user_id' => $this->user->id,
            'type' => $type,
            'punched_at' => $datetime,
            'source' => AttendanceLog::SOURCE_KIOSK,
            'identifier_method' => AttendanceLog::IDENTIFIER_PIN,
        ]);
    }

    private function monday(): string
    {
        return '2026-09-14'; // Monday
    }

    public function test_worked_minutes_exclude_the_unpaid_meal(): void
    {
        $this->punch(AttendanceLog::TYPE_CHECK_IN, $this->monday().' 08:00:00');
        $this->punch(AttendanceLog::TYPE_LUNCH_START, $this->monday().' 13:00:00');
        $this->punch(AttendanceLog::TYPE_LUNCH_END, $this->monday().' 14:00:00');
        $this->punch(AttendanceLog::TYPE_CHECK_OUT, $this->monday().' 17:00:00');

        $summary = $this->service->summaryFor($this->user, $this->monday());

        $this->assertSame(AttendanceDaySummary::STATUS_PRESENT, $summary->status);
        $this->assertSame(480, $summary->workedMinutes);
        $this->assertSame(0, $summary->lateMinutes);
        $this->assertSame(0, $summary->earlyLeaveMinutes);
        $this->assertSame(0, $summary->overtimeMinutes);
        $this->assertSame(480, $summary->expectedMinutes);
    }

    public function test_late_arrival_beyond_the_tolerance_is_counted(): void
    {
        $this->punch(AttendanceLog::TYPE_CHECK_IN, $this->monday().' 08:20:00');
        $this->punch(AttendanceLog::TYPE_CHECK_OUT, $this->monday().' 17:00:00');

        $summary = $this->service->summaryFor($this->user, $this->monday());

        $this->assertSame(20, $summary->lateMinutes);
        $this->assertSame(20, $summary->expectedLateMinutes());
    }

    public function test_late_arrival_within_the_tolerance_is_not_counted(): void
    {
        $this->punch(AttendanceLog::TYPE_CHECK_IN, $this->monday().' 08:08:00');
        $this->punch(AttendanceLog::TYPE_CHECK_OUT, $this->monday().' 17:00:00');

        $this->assertSame(0, $this->service->summaryFor($this->user, $this->monday())->lateMinutes);
    }

    public function test_shift_tolerance_override_is_respected(): void
    {
        $this->shift->update(['late_tolerance_minutes' => 25]);

        $this->punch(AttendanceLog::TYPE_CHECK_IN, $this->monday().' 08:20:00');
        $this->punch(AttendanceLog::TYPE_CHECK_OUT, $this->monday().' 17:00:00');

        $this->assertSame(0, $this->service->summaryFor($this->user, $this->monday())->lateMinutes);
    }

    public function test_early_leave_minutes_are_counted(): void
    {
        $this->punch(AttendanceLog::TYPE_CHECK_IN, $this->monday().' 08:00:00');
        $this->punch(AttendanceLog::TYPE_CHECK_OUT, $this->monday().' 16:30:00');

        $this->assertSame(30, $this->service->summaryFor($this->user, $this->monday())->earlyLeaveMinutes);
    }

    public function test_overtime_beyond_the_expected_minutes_is_counted(): void
    {
        $this->punch(AttendanceLog::TYPE_CHECK_IN, $this->monday().' 08:00:00');
        $this->punch(AttendanceLog::TYPE_LUNCH_START, $this->monday().' 13:00:00');
        $this->punch(AttendanceLog::TYPE_LUNCH_END, $this->monday().' 14:00:00');
        $this->punch(AttendanceLog::TYPE_CHECK_OUT, $this->monday().' 18:00:00');

        $summary = $this->service->summaryFor($this->user, $this->monday());

        $this->assertSame(540, $summary->workedMinutes);
        $this->assertSame(60, $summary->overtimeMinutes);
    }

    public function test_absence_is_detected_on_a_workday_without_punches(): void
    {
        // The clock is one day after the Monday under test.
        Carbon::setTestNow('2026-09-15 10:00:00');

        $summary = $this->service->summaryFor($this->user, $this->monday());

        $this->assertSame(AttendanceDaySummary::STATUS_ABSENT, $summary->status);
        $this->assertSame('Falta injustificada', $summary->statusLabel());
        $this->assertFalse($summary->hasPunches());
    }

    public function test_a_workday_without_punches_today_is_not_yet_an_absence(): void
    {
        // The clock sits on the same Monday under test.
        Carbon::setTestNow('2026-09-14 10:00:00');

        $summary = $this->service->summaryFor($this->user, $this->monday());

        $this->assertSame(AttendanceDaySummary::STATUS_NO_RECORD, $summary->status);
        $this->assertSame('Sin registro', $summary->statusLabel());
    }

    public function test_a_future_workday_without_punches_is_not_yet_an_absence(): void
    {
        Carbon::setTestNow('2026-09-10 10:00:00');

        $summary = $this->service->summaryFor($this->user, $this->monday());

        $this->assertSame(AttendanceDaySummary::STATUS_NO_RECORD, $summary->status);
    }

    public function test_rest_day_keeps_the_shift_for_reference(): void
    {
        // 2026-09-13 is a Sunday; the shift only covers monday to friday.
        $sunday = '2026-09-13';

        $this->punch(AttendanceLog::TYPE_CHECK_IN, $sunday.' 09:00:00');
        $this->punch(AttendanceLog::TYPE_CHECK_OUT, $sunday.' 13:00:00');

        $summary = $this->service->summaryFor($this->user, $sunday);

        $this->assertSame(AttendanceDaySummary::STATUS_REST_DAY, $summary->status);
        $this->assertFalse($summary->isWorkday);
        $this->assertSame(240, $summary->workedMinutes);
        // A worked rest day counts every minute as overtime.
        $this->assertSame(240, $summary->overtimeMinutes);
    }

    public function test_paid_meal_counts_as_worked_time(): void
    {
        $this->shift->update(['is_meal_paid' => true]);

        $this->punch(AttendanceLog::TYPE_CHECK_IN, $this->monday().' 08:00:00');
        $this->punch(AttendanceLog::TYPE_LUNCH_START, $this->monday().' 13:00:00');
        $this->punch(AttendanceLog::TYPE_LUNCH_END, $this->monday().' 14:00:00');
        $this->punch(AttendanceLog::TYPE_CHECK_OUT, $this->monday().' 17:00:00');

        $summary = $this->service->summaryFor($this->user, $this->monday());

        $this->assertSame(540, $summary->workedMinutes);
        $this->assertSame(540, $summary->expectedMinutes);
        $this->assertSame(0, $summary->overtimeMinutes);
    }

    public function test_late_override_is_applied(): void
    {
        AttendanceDayOverride::create([
            'user_id' => $this->user->id,
            'date' => $this->monday(),
            'late_ignored' => true,
            'notes' => 'Retardo justificado por tráfico',
        ]);

        $this->punch(AttendanceLog::TYPE_CHECK_IN, $this->monday().' 08:20:00');
        $this->punch(AttendanceLog::TYPE_CHECK_OUT, $this->monday().' 17:00:00');

        $summary = $this->service->summaryFor($this->user, $this->monday());

        $this->assertSame(20, $summary->lateMinutes);
        $this->assertTrue($summary->lateIgnored);
        $this->assertSame(0, $summary->expectedLateMinutes());
        $this->assertSame('Retardo justificado por tráfico', $summary->notes);
    }

    public function test_no_schedule_status_when_the_collaborator_has_no_assignment(): void
    {
        $other = User::factory()->create(['is_active' => true]);

        $summary = $this->service->summaryFor($other, $this->monday());

        $this->assertSame(AttendanceDaySummary::STATUS_NO_SCHEDULE, $summary->status);
        $this->assertFalse($summary->hasSchedule);
    }

    public function test_incidents_mark_the_day_status(): void
    {
        Incident::create([
            'user_id' => $this->user->id,
            'type' => Incident::TYPE_MEDICAL_LEAVE,
            'start_date' => $this->monday(),
            'end_date' => $this->monday(),
            'days' => 1,
            'status' => Incident::STATUS_APPROVED,
        ]);

        $summary = $this->service->summaryFor($this->user, $this->monday());

        $this->assertSame(AttendanceDaySummary::STATUS_INCIDENT, $summary->status);
        $this->assertSame('Incapacidad médica', $summary->incidentType);
    }
}
