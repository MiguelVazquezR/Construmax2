<?php

namespace App\Http\Controllers\Config;

use App\Http\Controllers\Controller;
use App\Models\NotificationSetting;
use App\Models\User;
use App\Services\Notifications\NotificationService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Http\RedirectResponse;

class NotificationController extends Controller
{
    public function __construct(
        private readonly NotificationService $notificationService,
    ) {}

    public function index(Request $request): Response
    {
        if (!$request->user()->can('config.notifications')) {
            abort(403);
        }

        $users = User::select('id', 'name', 'email')
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        $settings = NotificationSetting::with('user')->get()
            ->groupBy('notification_type');

        return Inertia::render('Config/Notifications/Index', [
            'users'    => $users,
            'settings' => $settings,
            'types'    => $this->notificationService->getTypes(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        if (!$request->user()->can('config.notifications')) {
            abort(403);
        }

        $validated = $request->validate([
            'user_id'           => ['required', 'integer', 'exists:users,id'],
            'notification_types' => ['required', 'array'],
            'notification_types.*' => ['string', 'in:' . implode(',', array_keys(NotificationSetting::TYPES))],
        ]);

        $this->notificationService->syncSettings($validated['user_id'], $validated['notification_types']);

        return back()->with('success', 'Notification settings saved successfully.');
    }

    public function destroy(Request $request, NotificationSetting $setting): RedirectResponse
    {
        if (!$request->user()->can('config.notifications')) {
            abort(403);
        }

        $setting->delete();

        return back()->with('success', 'Notification setting removed.');
    }

    /**
     * Fetch recent notifications for the bell dropdown.
     */
    public function fetch(Request $request): \Illuminate\Http\JsonResponse
    {
        $notifications = $request->user()
            ->notifications()
            ->latest()
            ->take(20)
            ->get();

        return response()->json([
            'notifications' => $notifications,
            'unread_count'  => $request->user()->unreadNotifications()->count(),
        ]);
    }

    /**
     * Mark a single notification as read.
     */
    public function markRead(Request $request, string $id): \Illuminate\Http\JsonResponse
    {
        $notification = $request->user()->notifications()->findOrFail($id);
        $notification->markAsRead();

        return response()->json(['ok' => true]);
    }

    /**
     * Mark all notifications as read.
     */
    public function markAllRead(Request $request): \Illuminate\Http\JsonResponse
    {
        $request->user()->unreadNotifications->markAsRead();

        return response()->json(['ok' => true]);
    }

    /**
     * Delete a single notification.
     */
    public function deleteOne(Request $request, string $id): \Illuminate\Http\JsonResponse
    {
        $notification = $request->user()->notifications()->findOrFail($id);
        $notification->delete();

        return response()->json(['ok' => true]);
    }

    /**
     * Delete all notifications for the user.
     */
    public function deleteAll(Request $request): \Illuminate\Http\JsonResponse
    {
        $request->user()->notifications()->delete();

        return response()->json(['ok' => true]);
    }
}
