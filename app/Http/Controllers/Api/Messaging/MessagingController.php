<?php

namespace App\Http\Controllers\Api\Messaging;

use App\Http\Controllers\Controller;
use App\Http\Requests\Messaging\SendMessageRequest;
use App\Http\Resources\Messaging\MessageResource;
use App\Http\Resources\Messaging\MessageCollection;
use App\Models\Dating\Message;
use App\Models\Dating\UserMatch;
use App\Events\MessageSent;
use App\Notifications\Dating\MessageNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class MessagingController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
    }

    /**
     * Get messages for a specific match
     */
    public function getMessages(Request $request, UserMatch $match): JsonResponse
    {
        // Check authorization
        if (!Auth::user()->can('view', $match)) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // Pagination parameters
        $limit = min(50, $request->input('limit', 20));
        $page = $request->input('page', 1);

        // Get messages (newest first for pagination, will be reversed in the front-end)
        $messages = Message::where('match_id', $match->id)
            ->with('sender')
            ->orderBy('created_at', 'desc')
            ->paginate($limit, ['*'], 'page', $page);

        // Update last_read_at timestamp for the current user's match
        if ($match->user1_id === Auth::id()) {
            $match->update(['user1_last_read_at' => now()]);
        } else {
            $match->update(['user2_last_read_at' => now()]);
        }

        // Update user's current_chat_id to prevent push notifications while viewing
        Auth::user()->update([
            'current_chat_id' => $match->id,
            'last_active_at' => now()
        ]);

        return response()->json([
            'status' => 'success',
            'data' => [
                'messages' => new MessageCollection($messages),
                'pagination' => [
                    'current_page' => $messages->currentPage(),
                    'total_pages' => $messages->lastPage(),
                    'total_messages' => $messages->total(),
                    'per_page' => $messages->perPage()
                ],
                'match' => [
                    'id' => $match->id,
                    'created_at' => $match->created_at->toISOString(),
                    'compatibility_score' => $match->compatibility_score ?? 0
                ],
                'unread_count' => $this->getUnreadCount($match)
            ]
        ]);
    }

    /**
     * Send a new message in a match
     */
    public function sendMessage(SendMessageRequest $request, UserMatch $match): JsonResponse
    {
        // Check authorization
        if (!Auth::user()->can('message', $match)) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        try {
            DB::beginTransaction();

            // Create the message
            $message = Message::create([
                'match_id' => $match->id,
                'sender_id' => Auth::id(),
                'content' => $request->input('content'),
                'type' => $request->input('type', 'text'),
                'meta' => $request->input('meta'),
            ]);

            // Update match's last_message_at
            $match->update([
                'last_message_at' => now(),
                'has_interaction' => true
            ]);

            // Determine recipient
            $recipientId = $match->user1_id === Auth::id() ? $match->user2_id : $match->user1_id;
            $recipient = \App\Models\User::find($recipientId);

            // Broadcast event for real-time updates
            broadcast(new MessageSent($message))->toOthers();

            // Send notification
            $recipient->notify(new MessageNotification(Auth::user(), $message, $match));

            DB::commit();

            return response()->json([
                'status' => 'success',
                'data' => [
                    'message' => new MessageResource($message),
                    'sent_at' => $message->created_at->toISOString()
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to send message',
                'debug' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Mark all messages in a match as read
     */
    public function markAsRead(UserMatch $match): JsonResponse
    {
        // Check authorization
        if (!Auth::user()->can('view', $match)) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        try {
            // Update last_read_at timestamp for the current user
            if ($match->user1_id === Auth::id()) {
                $match->update(['user1_last_read_at' => now()]);
            } else {
                $match->update(['user2_last_read_at' => now()]);
            }

            return response()->json([
                'status' => 'success',
                'data' => [
                    'unread_count' => 0,
                    'read_at' => now()->toISOString()
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to mark messages as read',
                'debug' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Get unread count for a match
     */
    private function getUnreadCount(UserMatch $match): int
    {
        $lastReadAt = $match->user1_id === Auth::id()
            ? $match->user1_last_read_at
            : $match->user2_last_read_at;

        if (!$lastReadAt) {
            return Message::where('match_id', $match->id)
                ->where('sender_id', '!=', Auth::id())
                ->count();
        }

        return Message::where('match_id', $match->id)
            ->where('sender_id', '!=', Auth::id())
            ->where('created_at', '>', $lastReadAt)
            ->count();
    }


    /**
     * Get unread message count for all matches
     */
    public function getUnreadMessageCount(): JsonResponse
    {
        $user = Auth::user();

        try {
            // Get all user matches
            $matches = UserMatch::where('user1_id', $user->id)
                ->orWhere('user2_id', $user->id)
                ->get();

            $totalUnread = 0;
            $matchesWithUnread = [];

            foreach ($matches as $match) {
                $unreadCount = $this->getUnreadCount($match);

                if ($unreadCount > 0) {
                    $matchesWithUnread[] = [
                        'match_id' => $match->id,
                        'unread_count' => $unreadCount,
                        'last_message_at' => $match->last_message_at?->toISOString()
                    ];

                    $totalUnread += $unreadCount;
                }
            }

            return response()->json([
                'status' => 'success',
                'data' => [
                    'total_unread' => $totalUnread,
                    'matches' => $matchesWithUnread
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to get unread message count',
                'debug' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Send typing indicator
     */
    public function sendTypingIndicator(Request $request, UserMatch $match): JsonResponse
    {
        // Check authorization
        if (!Auth::user()->can('message', $match)) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $isTyping = $request->input('is_typing', true);

        // Broadcast typing event
        broadcast(new UserTyping(Auth::user(), $match, $isTyping))->toOthers();

        return response()->json([
            'status' => 'success',
            'data' => [
                'match_id' => $match->id,
                'is_typing' => $isTyping
            ]
        ]);
    }
}
