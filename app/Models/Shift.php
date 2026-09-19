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

    public const TYPES = [
        self::TYPE_FIXED => 'Fijo',
        self::TYPE_FLEXIBLE => 'Flexible',
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
        'required_daily_hours',
        'late_tolerance_minutes',
        'is_active',
        'description',
    ];

    protected $casts = [
        'days' => 'array',
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

    public function isFlexible(): bool
    {
        return $this->type === self::TYPE_FLEXIBLE;
    }

    /**
     * Whether the given date is a working day for this shift.
     */
    public function isWorkday(CarbonInterface $date): bool
    {
        return in_array((int) $date->isoWeekday(), $this->days ?? [], true);
    }

    /**
     * Expected minutes per working day (net of unpaid meal time).
     */
    public function expectedDailyMinutes(): int
    {
        if ($this->isFlexible() || ! $this->start_time || ! $this->end_time) {
            $hours = (float) ($this->required_daily_hours ?? 0);

            return (int) round($hours * 60);
        }

        $start = Carbon::parse($this->start_time);
        $end = Carbon::parse($this->end_time);

        if ($end->lessThanOrEqualTo($start)) {
            $end->addDay();
        }

        $minutes = $start->diffInMinutes($end);

        if (! $this->is_meal_paid) {
            $minutes -= (int) $this->meal_minutes;
        }

        return max(0, (int) $minutes);
    }

    /**
     * Expected start datetime for a given date (null for flexible shifts).
     */
    public function expectedStartFor(CarbonInterface $date): ?Carbon
    {
        if ($this->isFlexible() || ! $this->start_time) {
            return null;
        }

        return Carbon::parse($date->toDateString().' '.$this->start_time);
    }

    /**
     * Expected end datetime for a given date (null for flexible shifts).
     */
    public function expectedEndFor(CarbonInterface $date): ?Carbon
    {
        if ($this->isFlexible() || ! $this->end_time) {
            return null;
        }

        $start = Carbon::parse($date->toDateString().' '.$this->start_time);
        $end = Carbon::parse($date->toDateString().' '.$this->end_time);

        if ($end->lessThanOrEqualTo($start)) {
            $end->addDay();
        }

        return $end;
    }
}
