<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Deposit extends Model implements HasMedia
{
    use HasFactory;
    use InteractsWithMedia;

    protected $fillable = [
        'technician_id',
        'technician_bank_account_id',
        'ticket_id',
        'budget_id',
        'deposit_type_id',
        'amount',
        'shift',
        'scheduled_date',
        'status',
        'created_by',
        'approved_by',
        'approved_at',
        'completed_at',
        'commission_amount',
        'technician_payment_id',
        'notes',
    ];

    protected $casts = [
        'approved_at' => 'datetime',
        'completed_at' => 'datetime',
        'scheduled_date' => 'date',
        'amount' => 'decimal:2',
        'commission_amount' => 'decimal:2',
    ];

    // --- Relationships ---

    public function technician(): BelongsTo
    {
        return $this->belongsTo(Technician::class);
    }

    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(TechnicianBankAccount::class, 'technician_bank_account_id');
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    public function budget(): BelongsTo
    {
        return $this->belongsTo(Budget::class);
    }

    public function depositType(): BelongsTo
    {
        return $this->belongsTo(DepositType::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function technicianPayment(): BelongsTo
    {
        return $this->belongsTo(TechnicianPayment::class);
    }

    // --- Media ---

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('voucher')->singleFile();
    }

    // --- Scopes ---

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', 'pending');
    }

    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('status', 'approved');
    }

    public function scopeCompleted(Builder $query): Builder
    {
        return $query->where('status', 'completed');
    }

    public function scopeForDate(Builder $query, string $date): Builder
    {
        return $query->whereDate('scheduled_date', $date);
    }

    public function scopeForShift(Builder $query, string $shift): Builder
    {
        return $query->where('shift', $shift);
    }

    public function scopeFilter(Builder $query, array $filters): Builder
    {
        return $query
            ->when($filters['search'] ?? null, function ($q, $s) {
                $q->whereHas('technician.user', function ($q) use ($s) {
                    $q->where('name', 'like', "%{$s}%");
                });
            })
            ->when($filters['status'] ?? null, fn ($q, $s) => $q->where('status', $s))
            ->when($filters['shift'] ?? null, fn ($q, $s) => $q->where('shift', $s));
    }
}
