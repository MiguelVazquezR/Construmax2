<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkAcceptanceReport extends Model
{
    use HasFactory;

    protected $table = 'work_acceptance_reports';

    protected $fillable = [
        'ticket_id',
        'report_date',
        'work_description',
        'on_site_start',
        'on_site_end',
        'technician_comments',
        'client_comments',
        'manager_name',
        'signature_data',
        'signatory_name',
        'signed_at',
        'is_signed',
        'created_by',
    ];

    protected $casts = [
        'report_date'   => 'date',
        'on_site_start' => 'datetime',
        'on_site_end'   => 'datetime',
        'signed_at'     => 'datetime',
        'is_signed'     => 'boolean',
    ];

    // --- Relationships ---

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // --- Scopes ---

    public function scopeSigned(Builder $query): Builder
    {
        return $query->where('is_signed', true);
    }

    public function scopeUnsigned(Builder $query): Builder
    {
        return $query->where('is_signed', false);
    }

    // --- Helpers ---

    public function isSigned(): bool
    {
        return $this->is_signed && $this->signed_at !== null;
    }

    /**
     * Lock the report after signature — prevent any further edits.
     */
    public function lock(): bool
    {
        return $this->update(['is_signed' => true, 'signed_at' => now()]);
    }
}
