<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class VacationPeriod extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'user_id',
        'year_number',
        'start_date',
        'end_date',
        'entitled_days',
        'accrued_days',
        'taken_days',
        'is_customized',
        'premium_paid_at',
        'premium_notified_at',
        'created_by',
    ];

    protected $casts = [
        'year_number' => 'integer',
        'start_date' => 'date',
        'end_date' => 'date',
        'entitled_days' => 'decimal:2',
        'accrued_days' => 'decimal:2',
        'taken_days' => 'decimal:2',
        'is_customized' => 'boolean',
        'premium_paid_at' => 'date',
        'premium_notified_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeForUser(Builder $query, int $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    /**
     * The period already ended (its year of service was completed).
     */
    public function isCompleted(): bool
    {
        return $this->end_date->lessThan(CarbonImmutable::today());
    }

    /**
     * Shape shown by the vacations screen (periods and premiums table).
     */
    public function toPayload(): array
    {
        return [
            'id' => $this->id,
            'year_number' => $this->year_number,
            'start_date' => $this->start_date?->toDateString(),
            'end_date' => $this->end_date?->toDateString(),
            'entitled_days' => (float) $this->entitled_days,
            'accrued_days' => (float) $this->accrued_days,
            'taken_days' => (float) $this->taken_days,
            'is_completed' => $this->isCompleted(),
            'is_customized' => $this->is_customized,
            'premium_paid_at' => $this->premium_paid_at?->toDateString(),
        ];
    }
}
