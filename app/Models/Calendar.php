<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Calendar extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'type',
        'title',
        'description',
        'start_time',
        'end_time',
    ];

    protected $casts = [
        'start_time' => 'datetime',
        'end_time' => 'datetime',
    ];

    // Creador del evento
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    // Participantes invitados
    public function participants(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'calendar_participants')
                    ->withPivot('status', 'rejection_reason')
                    ->withTimestamps();
    }
}