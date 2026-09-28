<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PayslipDay extends Model
{
    use HasFactory;

    protected $fillable = [
        'payslip_id',
        'date',
        'status',
        'first_in',
        'lunch_start',
        'lunch_end',
        'last_out',
        'worked_minutes',
        'late_minutes',
        'overtime_minutes',
        'notes',
    ];

    protected $casts = [
        'date' => 'date',
        'worked_minutes' => 'integer',
        'late_minutes' => 'integer',
        'overtime_minutes' => 'integer',
    ];

    public function payslip(): BelongsTo
    {
        return $this->belongsTo(Payslip::class);
    }
}
