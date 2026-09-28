<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttendanceDevice extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'token_hash',
        'location',
        'notes',
        'registered_by',
        'registered_at',
        'last_seen_at',
        'last_ip',
        'last_user_agent',
        'is_active',
        'revoked_by',
        'revoked_at',
    ];

    protected $casts = [
        'registered_at' => 'datetime',
        'last_seen_at' => 'datetime',
        'revoked_at' => 'datetime',
        'is_active' => 'boolean',
    ];

    protected $hidden = [
        'token_hash',
    ];

    public function registeredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registered_by');
    }

    public function revokedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'revoked_by');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
