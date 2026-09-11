<?php

namespace App\Services\Notifications;

use App\Models\NotificationSetting;
use App\Models\User;
use Illuminate\Support\Collection;

class NotificationService
{
    public const TYPE_TICKET_NEEDS_CATALOG = 'ticket.needs-catalog';
    public const TYPE_CATALOG_APPROVED = 'catalog.approved';
    public const TYPE_TICKET_NEEDS_INVOICE = 'ticket.needs-invoice';
    public const TYPE_INVOICE_OVERDUE = 'invoice.overdue';
    public const TYPE_DEPOSIT_PENDING_APPROVAL = 'deposit.pending-approval';

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
     * Dispatch a notification to all active users holding any of the given
     * permissions (granted directly or through a role).
     */
    public function notifyUsersWithPermissions(array $permissions, $notification): void
    {
        $users = User::query()
            ->where('is_active', true)
            ->whereNotNull('email')
            ->where('email', '!=', '')
            ->permission($permissions)
            ->get();

        foreach ($users as $user) {
            $user->notify($notification);
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
