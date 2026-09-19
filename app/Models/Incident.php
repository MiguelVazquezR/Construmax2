<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Incident extends Model implements HasMedia
{
    use HasFactory;
    use InteractsWithMedia;

    // --- Types ---

    public const TYPE_ABSENCE_JUSTIFIED = 'absence_justified';

    public const TYPE_ABSENCE_UNJUSTIFIED = 'absence_unjustified';

    public const TYPE_MEDICAL_LEAVE = 'medical_leave';

    public const TYPE_PERMISSION_PAID = 'permission_paid';

    public const TYPE_PERMISSION_UNPAID = 'permission_unpaid';

    public const TYPE_VACATION = 'vacation';

    public const TYPE_OTHER = 'other';

    public const TYPES = [
        self::TYPE_ABSENCE_JUSTIFIED => 'Falta justificada',
        self::TYPE_ABSENCE_UNJUSTIFIED => 'Falta injustificada',
        self::TYPE_MEDICAL_LEAVE => 'Incapacidad médica',
        self::TYPE_PERMISSION_PAID => 'Permiso con goce de sueldo',
        self::TYPE_PERMISSION_UNPAID => 'Permiso sin goce de sueldo',
        self::TYPE_VACATION => 'Vacaciones',
        self::TYPE_OTHER => 'Otro',
    ];

    /**
     * Types paid at full salary when they have no explicit override.
     *
     * @var array<int, string>
     */
    public const PAID_TYPES = [
        self::TYPE_ABSENCE_JUSTIFIED,
        self::TYPE_PERMISSION_PAID,
        self::TYPE_VACATION,
    ];

    // --- Statuses ---

    public const STATUS_APPROVED = 'approved';

    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'user_id',
        'type',
        'start_date',
        'end_date',
        'days',
        'is_paid',
        'status',
        'vacation_request_id',
        'notes',
        'created_by',
        'approved_by',
        'approved_at',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'days' => 'decimal:2',
        'is_paid' => 'boolean',
        'approved_at' => 'datetime',
    ];

    protected $appends = [
        'type_label',
        'support_url',
        'support_name',
    ];

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('support')->singleFile();
    }

    // --- Relationships ---

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function vacationRequest(): BelongsTo
    {
        return $this->belongsTo(VacationRequest::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    // --- Accessors ---

    public function getTypeLabelAttribute(): string
    {
        return self::TYPES[$this->type] ?? $this->type;
    }

    public function getSupportUrlAttribute(): ?string
    {
        return $this->getFirstMediaUrl('support') ?: null;
    }

    public function getSupportNameAttribute(): ?string
    {
        return $this->getFirstMedia('support')?->file_name;
    }

    // --- Scopes ---

    public function scopeForUser(Builder $query, int $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_APPROVED);
    }

    public function scopeCoveringDate(Builder $query, CarbonInterface|string $date): Builder
    {
        $value = $date instanceof CarbonInterface ? $date->toDateString() : $date;

        return $query
            ->whereDate('start_date', '<=', $value)
            ->where(function (Builder $query) use ($value) {
                $query->whereNull('end_date')
                    ->orWhereDate('end_date', '>=', $value);
            });
    }

    public function scopeOverlapping(Builder $query, CarbonInterface|string $start, CarbonInterface|string $end): Builder
    {
        $start = $start instanceof CarbonInterface ? $start->toDateString() : $start;
        $end = $end instanceof CarbonInterface ? $end->toDateString() : $end;

        return $query
            ->whereDate('start_date', '<=', $end)
            ->where(function (Builder $query) use ($start) {
                $query->whereNull('end_date')
                    ->orWhereDate('end_date', '>=', $start);
            });
    }

    // --- Pay rules ---

    /**
     * Whether the incident is paid, using the explicit override when present
     * and the default rule of its type otherwise.
     */
    public function resolvedIsPaid(?PayrollSetting $settings = null): bool
    {
        if ($this->is_paid !== null) {
            return (bool) $this->is_paid;
        }

        if ($this->type === self::TYPE_MEDICAL_LEAVE) {
            return (bool) ($settings ?? PayrollSetting::current())->incapacity_paid;
        }

        return in_array($this->type, self::PAID_TYPES, true);
    }

    /**
     * Fraction of the daily salary paid for this incident (0.0 - 1.0).
     */
    public function payPercentage(?PayrollSetting $settings = null): float
    {
        $settings ??= PayrollSetting::current();

        if ($this->type === self::TYPE_MEDICAL_LEAVE) {
            return $this->resolvedIsPaid($settings)
                ? ((int) $settings->incapacity_pay_percentage) / 100
                : 0.0;
        }

        return $this->resolvedIsPaid($settings) ? 1.0 : 0.0;
    }

    /**
     * Calendar days covered by the incident (inclusive).
     */
    public function calendarDays(): float
    {
        $start = $this->start_date;
        $end = $this->end_date ?? $this->start_date;

        return (float) ($start->diffInDays($end) + 1);
    }
}
