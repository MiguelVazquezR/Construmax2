<?php

namespace App\Services\Payroll;

use App\Models\Shift;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Computed summary of a collaborator's attendance for a single day.
 */
final class AttendanceDaySummary
{
    public const STATUS_PRESENT = 'present';

    public const STATUS_ABSENT = 'absent';

    public const STATUS_REST_DAY = 'rest_day';

    public const STATUS_NO_SCHEDULE = 'no_schedule';

    public const STATUS_HOLIDAY = 'holiday';

    public const STATUS_INCIDENT = 'incident';

    public const STATUS_LABELS = [
        self::STATUS_PRESENT => 'Asistió',
        self::STATUS_ABSENT => 'Falta',
        self::STATUS_REST_DAY => 'Día de descanso',
        self::STATUS_NO_SCHEDULE => 'Sin horario',
        self::STATUS_HOLIDAY => 'Día festivo',
        self::STATUS_INCIDENT => 'Incidencia',
    ];

    /**
     * @param  Collection<int, \App\Models\AttendanceLog>  $punches
     */
    public function __construct(
        public readonly CarbonImmutable $date,
        public readonly bool $hasSchedule,
        public readonly bool $isWorkday,
        public readonly ?Shift $shift,
        public readonly string $status,
        public readonly int $workedMinutes,
        public readonly int $pausedMinutes,
        public readonly int $lateMinutes,
        public readonly bool $lateIgnored,
        public readonly int $earlyLeaveMinutes,
        public readonly int $overtimeMinutes,
        public readonly int $expectedMinutes,
        public readonly ?CarbonInterface $firstIn,
        public readonly ?CarbonInterface $lastOut,
        public readonly ?CarbonInterface $lunchStart,
        public readonly ?CarbonInterface $lunchEnd,
        public readonly bool $isWorking,
        public readonly bool $isPaused,
        public readonly Collection $punches,
        public readonly ?string $incidentType = null,
        public readonly ?string $incidentTypeKey = null,
        public readonly ?float $incidentPayPercentage = null,
        public readonly ?string $holidayName = null,
        public readonly ?string $notes = null,
    ) {}

    public function hasPunches(): bool
    {
        return $this->punches->isNotEmpty();
    }

    public function expectedLateMinutes(): int
    {
        return $this->lateIgnored ? 0 : $this->lateMinutes;
    }

    public function statusLabel(): string
    {
        if ($this->status === self::STATUS_INCIDENT && $this->incidentType) {
            return $this->incidentType;
        }

        if ($this->status === self::STATUS_HOLIDAY && $this->holidayName) {
            return $this->holidayName;
        }

        return self::STATUS_LABELS[$this->status] ?? $this->status;
    }
}
