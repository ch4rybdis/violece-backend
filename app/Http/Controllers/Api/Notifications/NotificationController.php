<?php

namespace App\Http\Controllers\Api\Notifications;

use App\Http\Controllers\Controller;
use App\Http\Resources\Notifications\NotificationResource;
use App\Http\Resources\Notifications\NotificationCollection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class NotificationController extends Controller
{
    /**
     * Get user's notifications
     */
    public function index(Request $request): JsonResponse
    {
        $user = Auth::user();

        // Pagination
        $limit = min(50, $request->input('limit', 20));
        $page = $request->input('page', 1);

        // Filter by type
        $type = $request->input('type');

        $query = $user->notifications();

        if ($type) {
            $query->where('data->type', $type);
        }

        $notifications = $query->paginate($limit, ['*'], 'page', $page);

        return response()->json([
            'status' => 'success',
            'data' => [
                'notifications' => new NotificationCollection($notifications),
                'pagination' => [
                    'current_page' => $notifications->currentPage(),
                    'total_pages' => $notifications->lastPage(),
                    'total_notifications' => $notifications->total(),
                    'per_page' => $notifications->perPage()
                ],
                'unread_count' => $user->unreadNotifications()->count()
            ]
        ]);
    }

    /**
     * Mark notification as read
     */
    public function markAsRead(string $id): JsonResponse
    {
        $user = Auth::user();

        $notification = $user->notifications()->where('id', $id)->first();

        if (!$notification) {
            return response()->json([
                'status' => 'error',
                'message' => 'Notification not found'
            ], 404);
        }

        $notification->markAsRead();

        return response()->json([
            'status' => 'success',
            'message' => 'Notification marked as read',
            'data' => [
                'notification' => new NotificationResource($notification),
                'unread_count' => $user->unreadNotifications()->count()
            ]
        ]);
    }

    /**
     * Mark all notifications as read
     */
    public function markAllAsRead(): JsonResponse
    {
        $user = Auth::user();

        $user->unreadNotifications->markAsRead();

        return response()->json([
            'status' => 'success',
            'message' => 'All notifications marked as read',
            'data' => [
                'unread_count' => 0
            ]
        ]);
    }

    /**
     * Update FCM token for push notifications
     */
    public function updateFcmToken(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'token' => 'required|string',
            'device_type' => 'required|string|in:ios,android,web',
            'device_id' => 'sometimes|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $user = Auth::user();

            // Store the FCM token
            $fcmTokens = $user->fcm_tokens ?? [];

            // Check if token already exists
            $tokenExists = false;
            foreach ($fcmTokens as $key => $fcmToken) {
                if ($fcmToken['token'] === $request->input('token')) {
                    // Update the existing token entry
                    $fcmTokens[$key] = [
                        'token' => $request->input('token'),
                        'device_type' => $request->input('device_type'),
                        'device_id' => $request->input('device_id'),
                        'updated_at' => now()->toISOString()
                    ];
                    $tokenExists = true;
                    break;
                }
            }

            // If token doesn't exist, add it
            if (!$tokenExists) {
                $fcmTokens[] = [
                    'token' => $request->input('token'),
                    'device_type' => $request->input('device_type'),
                    'device_id' => $request->input('device_id'),
                    'created_at' => now()->toISOString(),
                    'updated_at' => now()->toISOString()
                ];
            }

            // Update user with new tokens
            $user->update(['fcm_tokens' => $fcmTokens]);

            return response()->json([
                'status' => 'success',
                'message' => 'FCM token updated successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to update FCM token',
                'debug' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Update notification preferences
     */
    public function updatePreferences(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'push_matches' => 'sometimes|boolean',
            'push_messages' => 'sometimes|boolean',
            'push_likes' => 'sometimes|boolean',
            'push_events' => 'sometimes|boolean',
            'email_matches' => 'sometimes|boolean',
            'email_messages' => 'sometimes|boolean',
            'email_weekly_digest' => 'sometimes|boolean',
            'do_not_disturb' => 'sometimes|boolean',
            'quiet_hours_start' => 'sometimes|nullable|date_format:H:i',
            'quiet_hours_end' => 'sometimes|nullable|date_format:H:i',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $user = Auth::user();

            // Get current preferences or initialize with defaults
            $currentPreferences = $user->notification_preferences ?? [
                'push_matches' => true,
                'push_messages' => true,
                'push_likes' => true,
                'push_events' => true,
                'email_matches' => true,
                'email_messages' => false,
                'email_weekly_digest' => true,
                'do_not_disturb' => false,
                'quiet_hours_start' => null,
                'quiet_hours_end' => null,
            ];

            // Update with new preferences
            $newPreferences = array_merge($currentPreferences, $request->all());

            // Update user
            $user->update(['notification_preferences' => $newPreferences]);

            return response()->json([
                'status' => 'success',
                'message' => 'Notification preferences updated successfully',
                'data' => [
                    'notification_preferences' => $user->notification_preferences
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to update notification preferences',
                'debug' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }
}
