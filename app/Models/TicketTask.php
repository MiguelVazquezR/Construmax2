<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class TicketTask extends Model implements HasMedia
{
    use HasFactory;
    use InteractsWithMedia;

    protected $fillable = [
        'ticket_id',
        'user_id',
        'name',
        'description',
        'status',
        'start_date', // Nuevo campo
        'due_date',
        'completed_at',
    ];

    protected $casts = [
        'start_date' => 'datetime', // Casting
        'due_date' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}