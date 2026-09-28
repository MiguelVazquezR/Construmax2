<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Expense extends Model implements HasMedia
{
    use HasFactory;
    use InteractsWithMedia;

    // --- Statuses ---

    public const STATUS_PENDING = 'pending';

    public const STATUS_PAID = 'paid';

    public const STATUS_CANCELLED = 'cancelled';

    // --- Payment methods ---

    public const PAYMENT_METHOD_CASH = 'cash';

    public const PAYMENT_METHOD_TRANSFER = 'transfer';

    public const PAYMENT_METHOD_CARD = 'card';

    public const PAYMENT_METHOD_CHECK = 'check';

    public const PAYMENT_METHOD_OTHER = 'other';

    protected $fillable = [
        'folio',
        'expense_category_id',
        'ticket_id',
        'budget_id',
        'budget_concept_id',
        'deposit_id',
        'payroll_period_id',
        'concept',
        'reference',
        'notes',
        'amount',
        'commission_amount',
        'expense_date',
        'payment_method',
        'status',
        'is_commission',
        'created_by',
        'paid_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'commission_amount' => 'decimal:2',
        'expense_date' => 'date',
        'paid_at' => 'datetime',
        'is_commission' => 'boolean',
    ];

    protected $appends = [
        'receipt_url',
        'receipt_name',
        'receipts',
        'status_label',
    ];

    protected static function booted(): void
    {
        static::creating(function (Expense $expense) {
            if (empty($expense->folio)) {
                $expense->folio = static::generateFolio();
            }
        });
    }

    // --- Relationships ---

    public function category(): BelongsTo
    {
        return $this->belongsTo(ExpenseCategory::class, 'expense_category_id');
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    public function budget(): BelongsTo
    {
        return $this->belongsTo(Budget::class);
    }

    public function budgetConcept(): BelongsTo
    {
        return $this->belongsTo(BudgetConcept::class);
    }

    public function deposit(): BelongsTo
    {
        return $this->belongsTo(Deposit::class);
    }

    public function payrollPeriod(): BelongsTo
    {
        return $this->belongsTo(PayrollPeriod::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // --- Accessors ---

    public function getStatusLabelAttribute(): string
    {
        // Deposits pending completion read as "awaiting deposit" instead of "awaiting payment".
        if ($this->status === self::STATUS_PENDING && $this->deposit_id) {
            return 'Pendiente de depósito';
        }

        return static::statusLabels()[$this->status] ?? $this->status;
    }

    public function getPaymentMethodLabelAttribute(): ?string
    {
        return static::paymentMethodLabels()[$this->payment_method] ?? $this->payment_method;
    }

    public function getReceiptUrlAttribute(): ?string
    {
        return $this->getFirstMediaUrl('receipt') ?: null;
    }

    public function getReceiptNameAttribute(): ?string
    {
        return $this->getFirstMedia('receipt')?->file_name;
    }

    /**
     * All receipts attached to the expense.
     *
     * @return array<int, array{id: int, url: string, name: string}>
     */
    public function getReceiptsAttribute(): array
    {
        return $this->getMedia('receipt')
            ->map(fn ($media) => [
                'id' => $media->id,
                'url' => $media->getUrl(),
                'name' => $media->file_name,
            ])
            ->values()
            ->all();
    }

    // --- Media ---

    public function registerMediaCollections(): void
    {
        // A single expense may hold several receipts (invoice, transfer proof, etc.)
        $this->addMediaCollection('receipt');
    }

    // --- Scopes ---

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        $term = trim((string) $term);

        if ($term === '') {
            return $query;
        }

        return $query->where(function (Builder $query) use ($term) {
            $query->where('folio', 'like', "%{$term}%")
                ->orWhere('concept', 'like', "%{$term}%")
                ->orWhere('reference', 'like', "%{$term}%");
        });
    }

    public function scopeWithStatus(Builder $query, ?string $status): Builder
    {
        if (! $status || $status === 'all') {
            return $query;
        }

        return $query->where('status', $status);
    }

    public function scopeInCategory(Builder $query, mixed $categoryId): Builder
    {
        if (! $categoryId) {
            return $query;
        }

        $category = ExpenseCategory::find($categoryId);
        $costType = $category?->conceptCostType();

        return $query->where(function (Builder $query) use ($categoryId, $costType) {
            $query->where('expense_category_id', $categoryId);

            // The default categories ("Mano de obra" / "Materiales") also match
            // concept payments that display that cost type — expenses without
            // their own category whose budget concept carries the same type.
            if ($costType) {
                $query->orWhere(function (Builder $query) use ($costType) {
                    $query->whereNull('expense_category_id')
                        ->whereHas('budgetConcept', fn (Builder $sub) => $sub->where('type', $costType));
                });
            }
        });
    }

    public function scopeBetweenDates(Builder $query, ?string $from, ?string $to): Builder
    {
        if ($from) {
            $query->whereDate('expense_date', '>=', $from);
        }

        if ($to) {
            $query->whereDate('expense_date', '<=', $to);
        }

        return $query;
    }

    public function scopeWithPaymentMethod(Builder $query, ?string $method): Builder
    {
        if (! $method || $method === 'all') {
            return $query;
        }

        return $query->where('payment_method', $method);
    }

    public function scopeForBudget(Builder $query, mixed $budgetId): Builder
    {
        if (! $budgetId) {
            return $query;
        }

        return $query->where('budget_id', $budgetId);
    }

    /**
     * Filter by expense type: general (no budget), budget, commission, deposit or payroll.
     */
    public function scopeOfType(Builder $query, ?string $type): Builder
    {
        return match ($type) {
            'general' => $query->whereNull('budget_id'),
            'budget' => $query->whereNotNull('budget_id'),
            'commission' => $query->where('is_commission', true),
            'deposit' => $query->whereNotNull('deposit_id'),
            'payroll' => $query->whereNotNull('payroll_period_id'),
            default => $query,
        };
    }

    public function scopeWithFolio(Builder $query, ?string $term): Builder
    {
        $term = trim((string) $term);

        if ($term === '') {
            return $query;
        }

        return $query->where('folio', 'like', "%{$term}%");
    }

    // --- Catalogs ---

    /**
     * @return array<string, string>
     */
    public static function statusLabels(): array
    {
        return [
            static::STATUS_PENDING => 'Pendiente de pago',
            static::STATUS_PAID => 'Pagado',
            static::STATUS_CANCELLED => 'Cancelado',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function paymentMethodLabels(): array
    {
        return [
            static::PAYMENT_METHOD_CASH => 'Efectivo',
            static::PAYMENT_METHOD_TRANSFER => 'Transferencia',
            static::PAYMENT_METHOD_CARD => 'Tarjeta',
            static::PAYMENT_METHOD_CHECK => 'Cheque',
            static::PAYMENT_METHOD_OTHER => 'Otro',
        ];
    }

    // --- Helpers ---

    private static function generateFolio(): string
    {
        $number = (int) static::query()->max('id') + 1;

        do {
            $folio = 'GAS-'.str_pad((string) $number, 4, '0', STR_PAD_LEFT);
            $number++;
        } while (static::query()->where('folio', $folio)->exists());

        return $folio;
    }
}
