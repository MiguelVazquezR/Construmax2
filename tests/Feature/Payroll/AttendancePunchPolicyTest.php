<?php

namespace Tests\Feature\Payroll;

use App\Models\AttendanceLog;
use App\Models\PayrollProfile;
use App\Models\User;
use App\Services\Payroll\AttendancePunchPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class AttendancePunchPolicyTest extends TestCase
{
    use RefreshDatabase;

    private User $employee;

    private AttendancePunchPolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-11-12 07:00:00');

        $this->employee = User::factory()->create(['is_active' => true]);

        PayrollProfile::create([
            'user_id' => $this->employee->id,
            'employee_number' => 'EMP-0101',
            'is_attendance_subject' => true,
        ]);

        $this->policy = app(AttendancePunchPolicy::class);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_it_only_allows_the_entry_when_the_day_has_no_movements(): void
    {
        $options = collect($this->policy->optionsFor($this->employee))->keyBy('type');

        $this->assertTrue($options[AttendanceLog::TYPE_CHECK_IN]['allowed']);
        $this->assertFalse($options[AttendanceLog::TYPE_LUNCH_START]['allowed']);
        $this->assertFalse($options[AttendanceLog::TYPE_LUNCH_END]['allowed']);
        $this->assertFalse($options[AttendanceLog::TYPE_BREAK_START]['allowed']);
        $this->assertFalse($options[AttendanceLog::TYPE_BREAK_END]['allowed']);
        $this->assertFalse($options[AttendanceLog::TYPE_CHECK_OUT]['allowed']);
    }

    public function test_it_blocks_a_second_entry_and_offers_the_next_movements(): void
    {
        $this->punch(AttendanceLog::TYPE_CHECK_IN, '08:00');

        $options = collect($this->policy->optionsFor($this->employee))->keyBy('type');

        $this->assertFalse($options[AttendanceLog::TYPE_CHECK_IN]['allowed']);
        $this->assertSame(
            'Ya registraste tu entrada a las 08:00. Selecciona otro tipo de registro.',
            $options[AttendanceLog::TYPE_CHECK_IN]['reason']
        );
        $this->assertSame('08:00', $options[AttendanceLog::TYPE_CHECK_IN]['last_registered_at']);
        $this->assertTrue($options[AttendanceLog::TYPE_LUNCH_START]['allowed']);
        $this->assertTrue($options[AttendanceLog::TYPE_BREAK_START]['allowed']);
        $this->assertTrue($options[AttendanceLog::TYPE_CHECK_OUT]['allowed']);
    }

    public function test_it_blocks_the_lunch_start_while_the_lunch_is_open(): void
    {
        $this->punch(AttendanceLog::TYPE_CHECK_IN, '08:00');
        $this->punch(AttendanceLog::TYPE_LUNCH_START, '13:00');

        $options = collect($this->policy->optionsFor($this->employee))->keyBy('type');

        $this->assertFalse($options[AttendanceLog::TYPE_LUNCH_START]['allowed']);
        $this->assertSame(
            'Ya registraste el inicio de comida a las 13:00. Registra el fin de comida.',
            $options[AttendanceLog::TYPE_LUNCH_START]['reason']
        );
        $this->assertTrue($options[AttendanceLog::TYPE_LUNCH_END]['allowed']);
        $this->assertFalse($options[AttendanceLog::TYPE_BREAK_START]['allowed']);
        $this->assertTrue($options[AttendanceLog::TYPE_CHECK_OUT]['allowed']);
    }

    public function test_it_allows_lunch_and_permission_cycles_more_than_once(): void
    {
        $this->punch(AttendanceLog::TYPE_CHECK_IN, '08:00');
        $this->punch(AttendanceLog::TYPE_LUNCH_START, '13:00');
        $this->punch(AttendanceLog::TYPE_LUNCH_END, '14:00');
        $this->punch(AttendanceLog::TYPE_BREAK_START, '16:00');
        $this->punch(AttendanceLog::TYPE_BREAK_END, '16:15');

        $options = collect($this->policy->optionsFor($this->employee))->keyBy('type');

        $this->assertTrue($options[AttendanceLog::TYPE_LUNCH_START]['allowed']);
        $this->assertTrue($options[AttendanceLog::TYPE_BREAK_START]['allowed']);
        $this->assertTrue($options[AttendanceLog::TYPE_CHECK_OUT]['allowed']);
    }

    public function test_it_allows_a_new_entry_after_the_exit(): void
    {
        $this->punch(AttendanceLog::TYPE_CHECK_IN, '08:00');
        $this->punch(AttendanceLog::TYPE_CHECK_OUT, '12:00');

        $options = collect($this->policy->optionsFor($this->employee))->keyBy('type');

        $this->assertTrue($options[AttendanceLog::TYPE_CHECK_IN]['allowed']);
        $this->assertFalse($options[AttendanceLog::TYPE_CHECK_OUT]['allowed']);
        $this->assertSame(
            'Ya registraste tu salida a las 12:00. Registra una nueva entrada si vas a continuar.',
            $options[AttendanceLog::TYPE_CHECK_OUT]['reason']
        );
    }

    public function test_it_ignores_the_previous_day(): void
    {
        $this->punch(AttendanceLog::TYPE_CHECK_IN, '08:00', '2026-11-11');

        $options = collect($this->policy->optionsFor($this->employee))->keyBy('type');

        $this->assertTrue($options[AttendanceLog::TYPE_CHECK_IN]['allowed']);
        $this->assertFalse($options[AttendanceLog::TYPE_CHECK_OUT]['allowed']);
    }

    private function punch(string $type, string $time, string $date = '2026-11-12'): void
    {
        AttendanceLog::create([
            'user_id' => $this->employee->id,
            'type' => $type,
            'punched_at' => Carbon::parse("{$date} {$time}"),
            'source' => AttendanceLog::SOURCE_REMOTE,
            'identifier_method' => AttendanceLog::IDENTIFIER_MANUAL,
        ]);
    }
}
