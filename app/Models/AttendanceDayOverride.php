<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttendanceDayOverride extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'date',
        'late_ignored',
        'notes',
        'updated_by',
    ];

    protected $casts = [
        'date' => 'date',
        'late_ignored' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function editor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
