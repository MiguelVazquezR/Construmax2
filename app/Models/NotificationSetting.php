<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotificationSetting extends Model
{
    use HasFactory;

    protected $fillable = [
        'notification_type',
        'user_id',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public const TYPES = [
        'ticket.needs-catalog'       => 'Ticket necesita catálogo de costos',
        'catalog.created'            => 'Catálogo de costos generado',
        'ticket.needs-invoice'       => 'Ticket listo para facturar',
        'invoice.overdue'            => 'Vencimiento de factura',
        'deposit.pending-approval'   => 'Depósito pendiente de aprobación',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get users subscribed to a specific notification type.
     * Only returns users with valid email addresses.
     */
    public static function subscribersFor(string $type): \Illuminate\Support\Collection
    {
        return self::where('notification_type', $type)
            ->where('is_active', true)
            ->whereHas('user', fn ($q) => $q->whereNotNull('email')->where('email', '!=', ''))
            ->with('user')
            ->get()
            ->pluck('user');
    }
}
