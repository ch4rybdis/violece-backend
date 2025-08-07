<?php

namespace App\Notifications\Dating;

use App\Models\User;
use App\Models\Dating\Message;
use App\Models\Dating\UserMatch;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Messages\DatabaseMessage;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class MessageNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected $sender;
    protected $message;
    protected $match;

    public function __construct(User $sender, Message $message, UserMatch $match)
    {
        $this->sender = $sender;
        $this->message = $message;
        $this->match = $match;
    }

    public function via($notifiable)
    {
        $channels = ['database'];

        // Add push notification if user is not currently in this chat
        if ($notifiable->current_chat_id !== $this->match->id) {
            $channels[] = 'fcm'; // Firebase Cloud Messaging
        }

        return $channels;
    }

    public function toDatabase($notifiable)
    {
        return [
            'type' => 'new_message',
            'sender_id' => $this->sender->id,
            'sender_name' => $this->sender->first_name,
            'sender_photo' => $this->sender->getPrimaryPhotoUrl(),
            'match_id' => $this->match->id,
            'message_preview' => $this->getMessagePreview(),
            'sent_at' => $this->message->created_at->toISOString()
        ];
    }

    public function toFcm($notifiable)
    {
        return [
            'title' => "New message from {$this->sender->first_name}",
            'body' => $this->getMessagePreview(),
            'data' => [
                'type' => 'new_message',
                'match_id' => $this->match->id,
                'sender_id' => $this->sender->id
            ]
        ];
    }

    private function getMessagePreview(): string
    {
        if ($this->message->type === 'text') {
            return strlen($this->message->content) > 50
                ? substr($this->message->content, 0, 50) . '...'
                : $this->message->content;
        }

        return match($this->message->type) {
            'image' => '📷 Photo',
            'gif' => '🎭 GIF',
            'audio' => '🎵 Voice message',
            'location' => '📍 Location',
            default => 'New message'
        };
    }
}
