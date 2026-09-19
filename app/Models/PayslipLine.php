<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PayslipLine extends Model
{
    use HasFactory;

    public const TYPE_EARNING = 'earning';

    public const TYPE_DEDUCTION = 'deduction';

    protected $fillable = [
        'payslip_id',
        'concept',
        'type',
        'quantity',
        'unit_rate',
        'amount',
        'source',
        'sort_order',
    ];

    protected $casts = [
        'quantity' => 'decimal:2',
        'unit_rate' => 'decimal:2',
        'amount' => 'decimal:2',
        'sort_order' => 'integer',
    ];

    public function payslip(): BelongsTo
    {
        return $this->belongsTo(Payslip::class);
    }
}
