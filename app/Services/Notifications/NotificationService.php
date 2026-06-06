<?php

namespace App\Services\Notifications;

use App\Models\NotificationSetting;
use App\Models\User;
use Illuminate\Support\Collection;

class NotificationService
{
    public const TYPE_TICKET_NEEDS_CATALOG = 'ticket.needs-catalog';
    public const TYPE_CATALOG_CREATED = 'catalog.created';
    public const TYPE_TICKET_NEEDS_INVOICE = 'ticket.needs-invoice';
    public const TYPE_INVOICE_OVERDUE = 'invoice.overdue';

    /**
     * Get subscribers for a notification type.
     */
    public function getSubscribers(string $type): Collection
    {
        return NotificationSetting::subscribersFor($type);
    }

    /**
     * Dispatch a notification to all subscribers of a type.
     */
    public function notifySubscribers(string $type, $notification): void
    {
        $subscribers = $this->getSubscribers($type);

        foreach ($subscribers as $user) {
            if ($user && $user->email) {
                $user->notify($notification);
            }
        }
    }

    /**
     * Create or update notification settings for a user.
     */
    public function syncSettings(int $userId, array $settings): void
    {
        foreach (NotificationSetting::TYPES as $type => $label) {
            NotificationSetting::updateOrCreate(
                ['notification_type' => $type, 'user_id' => $userId],
                ['is_active' => in_array($type, $settings)]
            );
        }
    }

    /**
     * Get all notification types with their labels.
     */
    public function getTypes(): array
    {
        return NotificationSetting::TYPES;
    }
}
