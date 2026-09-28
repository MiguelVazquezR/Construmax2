<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShiftAssignment extends Model
{
    use HasFactory;

    public const TYPE_FIXED = 'fixed';

    public const TYPE_ROTATION = 'rotation';

    public const TYPES = [
        self::TYPE_FIXED => 'Fijo',
        self::TYPE_ROTATION => 'Rotativo',
    ];

    protected $fillable = [
        'user_id',
        'department',
        'type',
        'shift_id',
        'rotation',
        'start_date',
        'end_date',
        'is_active',
        'notes',
    ];

    protected $casts = [
        'rotation' => 'array',
        'start_date' => 'date',
        'end_date' => 'date',
        'is_active' => 'boolean',
    ];

    protected $appends = [
        'shift_name',
        'target_label',
        'rotation_labels',
    ];

    public function getShiftNameAttribute(): ?string
    {
        return $this->shift?->name;
    }

    public function getTargetLabelAttribute(): string
    {
        return $this->targetLabel();
    }

    /**
     * Rotation shift names in cycle order.
     *
     * @return array<int, string>
     */
    public function getRotationLabelsAttribute(): array
    {
        $ids = array_values(array_filter($this->rotation ?? []));

        if (empty($ids)) {
            return [];
        }

        $names = Shift::query()->whereIn('id', $ids)->pluck('name', 'id');

        return array_values(array_filter(array_map(fn ($id) => $names[$id] ?? null, $ids)));
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Individual fixed assignment effective today (the one resolved first by
     * the schedule resolver). Null when the collaborator falls back to their
     * department assignment.
     */
    public static function currentFor(User $user): ?self
    {
        return static::query()
            ->where('user_id', $user->id)
            ->where('type', self::TYPE_FIXED)
            ->where('is_active', true)
            ->effectiveOn(CarbonImmutable::today())
            ->orderByDesc('start_date')
            ->orderByDesc('id')
            ->first();
    }

    public function scopeEffectiveOn(Builder $query, CarbonInterface $date): Builder
    {
        return $query
            ->whereDate('start_date', '<=', $date->toDateString())
            ->where(function (Builder $query) use ($date) {
                $query->whereNull('end_date')
                    ->orWhereDate('end_date', '>=', $date->toDateString());
            });
    }

    public function isRotation(): bool
    {
        return $this->type === self::TYPE_ROTATION;
    }

    /**
     * Shift that applies to the given date, resolving weekly rotations.
     */
    public function shiftForDate(CarbonInterface $date): ?Shift
    {
        if (! $this->isRotation()) {
            return $this->shift;
        }

        $rotation = array_values(array_filter($this->rotation ?? []));

        if (empty($rotation)) {
            return $this->shift;
        }

        $start = $this->start_date->copy()->startOfDay();
        $reference = $start->copy()->startOfDay();

        $daysSinceStart = max(0, $reference->diffInDays($date->copy()->startOfDay(), false));
        $weekIndex = intdiv((int) $daysSinceStart, 7) % count($rotation);

        return Shift::find($rotation[$weekIndex]);
    }

    /**
     * Human readable description of who this assignment targets.
     */
    public function targetLabel(): string
    {
        return $this->user?->name ?? $this->department ?? 'Sin asignar';
    }
}
