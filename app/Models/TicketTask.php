<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

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
        'technician_notes',
    ];

    protected $casts = [
        'start_date' => 'datetime', // Casting
        'due_date' => 'datetime',
        'completed_at' => 'datetime',
    ];

    /**
     * Override Spatie's media() to always order by order_column.
     * Spatie v11's raw relationship lacks the orderBy, so eager-loaded
     * media (e.g. $ticket->load('tasks.media')) comes unsorted.
     */
    public function media(): MorphMany
    {
        return $this->morphMany(config('media-library.media_model', Media::class), 'model')
            ->orderBy('order_column');
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}