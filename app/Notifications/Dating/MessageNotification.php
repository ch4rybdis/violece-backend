<?php



namespace App\Notifications\Dating;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use App\Models\User;
use App\Models\Dating\UserMatch;
use App\Models\Dating\Message;

/**
 * Notification for new messages
 */
class MessageNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected User $sender;
    protected Message $message;
    protected UserMatch $match;

    public function __construct(User $sender, Message $message, UserMatch $match)
    {
        $this->sender = $sender;
        $this->message = $message;
        $this->match = $match;
        $this->queue = 'notifications';
    }

    public function via($notifiable): array
    {
        $channels = ['database'];

        // Only send push if user has messages enabled and isn't currently active in the chat
        if ($notifiable->notification_preferences['push_messages'] ?? true) {
            // Check if user is currently active in this chat (last seen < 30 seconds ago)
            $isActiveInChat = $notifiable->last_active_at &&
                $notifiable->last_active_at->diffInSeconds() < 30 &&
                $notifiable->current_chat_id === $this->match->id;

            if (!$isActiveInChat) {
                $channels[] = 'fcm';
            }
        }

        return $channels;
    }

    public function toDatabase($notifiable): array
    {
        return [
            'type' => 'message',
            'sender' => [
                'id' => $this->sender->id,
                'name' => $this->sender->first_name,
                'photo' => $this->sender->getPrimaryPhotoUrl(),
            ],
            'match_id' => $this->match->id,
            'message_id' => $this->message->id,
            'message_preview' => $this->getMessagePreview(),
            'created_at' => $this->message->created_at->toISOString(),
        ];
    }

    public function toFcm($notifiable): array
    {
        return [
            'title' => $this->sender->first_name,
            'body' => $this->getMessagePreview(),
            'data' => [
                'type' => 'message',
                'match_id' => $this->match->id,
                'message_id' => $this->message->id,
                'sender_id' => $this->sender->id,
                'deep_link' => "violece://matches/{$this->match->id}",
            ],
            'android' => [
                'channel_id' => 'messages',
                'priority' => 'high',
            ],
            'apns' => [
                'payload' => [
                    'aps' => [
                        'sound' => 'violece_message.wav',
                        'badge' => $notifiable->unreadNotifications()->count() + 1,
                    ],
                ],
            ],
        ];
    }

    private function getMessagePreview(): string
    {
        $content = $this->message->content;

        // Handle different message types
        switch ($this->message->type) {
            case 'text':
                return strlen($content) > 50 ? substr($content, 0, 47) . '...' : $content;
            case 'image':
                return '📸 Sent a photo';
            case 'gif':
                return '🎬 Sent a GIF';
            case 'audio':
                return '🎵 Sent a voice message';
            case 'location':
                return '📍 Shared location';
            default:
                return 'Sent a message';
        }
    }
}
