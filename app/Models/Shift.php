<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

class Shift extends Model
{
    use HasFactory;

    public const TYPE_FIXED = 'fixed';

    public const TYPE_FLEXIBLE = 'flexible';

    public const TYPE_PER_DAY = 'per_day';

    public const TYPES = [
        self::TYPE_FIXED => 'Fijo',
        self::TYPE_FLEXIBLE => 'Flexible',
        self::TYPE_PER_DAY => 'Por día',
    ];

    /**
     * When to use each shift type (shown on the shifts screen).
     *
     * @var array<string, string>
     */
    public const TYPE_DESCRIPTIONS = [
        self::TYPE_FIXED => 'El colaborador debe cumplir una hora de entrada y de salida. Úsalo cuando la jornada siempre empieza y termina a la misma hora.',
        self::TYPE_FLEXIBLE => 'El colaborador debe completar ciertas horas al día, pero puede elegir su hora de entrada y salida. Úsalo cuando no haya un horario estricto.',
        self::TYPE_PER_DAY => 'Cada día de la semana tiene su propio horario y tiempo de comida. Úsalo cuando el horario cambia según el día, por ejemplo los sábados.',
    ];

    /**
     * ISO weekday labels (1 = monday ... 7 = sunday).
     *
     * @var array<int, string>
     */
    public const DAYS = [
        1 => 'Lunes',
        2 => 'Martes',
        3 => 'Miércoles',
        4 => 'Jueves',
        5 => 'Viernes',
        6 => 'Sábado',
        7 => 'Domingo',
    ];

    protected $fillable = [
        'name',
        'type',
        'start_time',
        'end_time',
        'meal_minutes',
        'is_meal_paid',
        'days',
        'day_schedules',
        'required_daily_hours',
        'late_tolerance_minutes',
        'is_active',
        'description',
    ];

    protected $casts = [
        'days' => 'array',
        'day_schedules' => 'array',
        'is_meal_paid' => 'boolean',
        'is_active' => 'boolean',
        'required_daily_hours' => 'decimal:2',
        'meal_minutes' => 'integer',
        'late_tolerance_minutes' => 'integer',
    ];

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(ShiftAssignment::class);
    }

    /**
     * Active shifts ready to feed the shift selector of the user and
     * technician forms.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function optionList(): array
    {
        return static::query()
            ->active()
            ->orderBy('name')
            ->get([
                'id',
                'name',
                'type',
                'start_time',
                'end_time',
                'meal_minutes',
                'is_meal_paid',
                'days',
                'day_schedules',
                'required_daily_hours',
            ])
            ->toArray();
    }

    public function isFlexible(): bool
    {
        return $this->type === self::TYPE_FLEXIBLE;
    }

    public function isPerDay(): bool
    {
        return $this->type === self::TYPE_PER_DAY;
    }

    /**
     * Schedule configured for a weekday of a per-day shift (null on rest days
     * or when the day has no start/end times configured).
     *
     * @return array{start_time: string, end_time: string, meal_minutes: int}|null
     */
    public function dayScheduleFor(CarbonInterface $date): ?array
    {
        $schedules = $this->day_schedules ?? [];
        $weekday = $date->isoWeekday();
        $schedule = $schedules[(string) $weekday] ?? $schedules[$weekday] ?? null;

        if (! is_array($schedule) || empty($schedule['start_time']) || empty($schedule['end_time'])) {
            return null;
        }

        return [
            'start_time' => (string) $schedule['start_time'],
            'end_time' => (string) $schedule['end_time'],
            'meal_minutes' => (int) ($schedule['meal_minutes'] ?? 0),
        ];
    }

    /**
     * Whether the given date is a working day for this shift.
     */
    public function isWorkday(CarbonInterface $date): bool
    {
        if ($this->isPerDay()) {
            return $this->dayScheduleFor($date) !== null;
        }

        return in_array((int) $date->isoWeekday(), $this->days ?? [], true);
    }

    /**
     * Expected minutes per working day (net of unpaid meal time). Per-day
     * shifts use the schedule configured for the given date.
     */
    public function expectedDailyMinutes(?CarbonInterface $date = null): int
    {
        if ($this->isFlexible() || (! $this->isPerDay() && (! $this->start_time || ! $this->end_time))) {
            $hours = (float) ($this->required_daily_hours ?? 0);

            return (int) round($hours * 60);
        }

        if ($this->isPerDay()) {
            $schedule = $this->dayScheduleFor($date ?? Carbon::today());

            if (! $schedule) {
                return 0;
            }

            return $this->netMinutesFor($schedule['start_time'], $schedule['end_time'], $schedule['meal_minutes']);
        }

        if (! $this->start_time || ! $this->end_time) {
            return 0;
        }

        return $this->netMinutesFor($this->start_time, $this->end_time, (int) $this->meal_minutes);
    }

    /**
     * A single daily-hours figure for the shift. It keeps the payroll
     * profile's hours aligned with the assigned schedule (the manual
     * "hours per day" field was removed from the forms): fixed shifts
     * derive it from their schedule, flexible ones use their required
     * hours and per-day shifts average their configured weekdays.
     */
    public function representativeDailyHours(): float
    {
        if ($this->isPerDay()) {
            $minutes = [];

            foreach ($this->day_schedules ?? [] as $schedule) {
                if (! is_array($schedule) || empty($schedule['start_time']) || empty($schedule['end_time'])) {
                    continue;
                }

                $minutes[] = $this->netMinutesFor(
                    (string) $schedule['start_time'],
                    (string) $schedule['end_time'],
                    (int) ($schedule['meal_minutes'] ?? 0),
                );
            }

            return $minutes === [] ? 0.0 : round(array_sum($minutes) / count($minutes) / 60, 2);
        }

        return round($this->expectedDailyMinutes() / 60, 2);
    }

    /**
     * Net minutes between two times (unpaid meals excluded, overnight
     * ranges supported).
     */
    private function netMinutesFor(string $start, string $end, int $mealMinutes): int
    {
        $startAt = Carbon::parse($start);
        $endAt = Carbon::parse($end);

        if ($endAt->lessThanOrEqualTo($startAt)) {
            $endAt->addDay();
        }

        $minutes = $startAt->diffInMinutes($endAt);

        if (! $this->is_meal_paid) {
            $minutes -= $mealMinutes;
        }

        return max(0, (int) $minutes);
    }

    /**
     * Expected start datetime for a given date (null for flexible shifts and
     * for rest days of a per-day shift).
     */
    public function expectedStartFor(CarbonInterface $date): ?Carbon
    {
        $startTime = $this->isPerDay()
            ? $this->dayScheduleFor($date)['start_time'] ?? null
            : $this->start_time;

        if ($this->isFlexible() || ! $startTime) {
            return null;
        }

        return Carbon::parse($date->toDateString().' '.$startTime);
    }

    /**
     * Expected end datetime for a given date (null for flexible shifts and
     * for rest days of a per-day shift).
     */
    public function expectedEndFor(CarbonInterface $date): ?Carbon
    {
        $schedule = $this->isPerDay() ? $this->dayScheduleFor($date) : null;
        $startTime = $this->isPerDay() ? $schedule['start_time'] ?? null : $this->start_time;
        $endTime = $this->isPerDay() ? $schedule['end_time'] ?? null : $this->end_time;

        if ($this->isFlexible() || ! $startTime || ! $endTime) {
            return null;
        }

        $start = Carbon::parse($date->toDateString().' '.$startTime);
        $end = Carbon::parse($date->toDateString().' '.$endTime);

        if ($end->lessThanOrEqualTo($start)) {
            $end->addDay();
        }

        return $end;
    }
}
