<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BudgetCatalog extends Model
{
    use HasFactory;

    public const STATUS_PENDING_APPROVAL = 'pending_approval';
    public const STATUS_APPROVED = 'approved';

    public const STATUS_LABELS = [
        self::STATUS_PENDING_APPROVAL => 'Pendiente de aprobación',
        self::STATUS_APPROVED         => 'Aprobado',
    ];

    protected $fillable = [
        'budget_id',
        'version',
        'subtotal',
        'iva',
        'total',
        'non_installation_labor',
        'labor_utility',
        'needs_special_authorization',
        'transfer_notes',
        'status',
        'approved_by',
        'approved_at',
    ];

    protected $casts = [
        'needs_special_authorization' => 'boolean',
        'approved_at' => 'datetime',
    ];

    public function budget(): BelongsTo
    {
        return $this->belongsTo(Budget::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(BudgetCatalogItem::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function isApproved(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }

    public function isPendingApproval(): bool
    {
        return $this->status === self::STATUS_PENDING_APPROVAL;
    }

    public function approve(int $userId): void
    {
        $this->update([
            'status'      => self::STATUS_APPROVED,
            'approved_by' => $userId,
            'approved_at' => now(),
        ]);
    }

    public function statusLabel(): string
    {
        return self::STATUS_LABELS[$this->status] ?? $this->status;
    }
}