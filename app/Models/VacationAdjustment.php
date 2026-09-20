<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VacationAdjustment extends Model
{
    use HasFactory;

    public const TYPE_INITIAL = 'initial';

    public const TYPE_GRANT = 'grant';

    public const TYPE_ADJUSTMENT = 'adjustment';

    public const TYPES = [
        self::TYPE_INITIAL => 'Saldo inicial',
        self::TYPE_GRANT => 'Días agregados',
        self::TYPE_ADJUSTMENT => 'Ajuste manual',
    ];

    protected $fillable = [
        'user_id',
        'type',
        'days',
        'reason',
        'created_by',
    ];

    protected $casts = [
        'days' => 'decimal:2',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeForUser(Builder $query, int $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    public function typeLabel(): string
    {
        return self::TYPES[$this->type] ?? $this->type;
    }

    /**
     * Only manual adjustments may discount days; the initial balance and the
     * granted days always add to the available balance.
     */
    public function allowsNegativeDays(): bool
    {
        return $this->type === self::TYPE_ADJUSTMENT;
    }

    /**
     * Shape shared by the collaborator profile and the vacations screen.
     */
    public function toPayload(): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'type_label' => $this->typeLabel(),
            'days' => (float) $this->days,
            'reason' => $this->reason,
            'author_name' => $this->author?->name,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
