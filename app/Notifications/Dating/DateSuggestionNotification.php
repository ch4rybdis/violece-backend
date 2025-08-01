<?php

namespace App\Notifications\Dating;

use App\Models\Dating\DateSuggestion;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class DateSuggestionNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected $dateSuggestion;
    protected $match;

    public function __construct(DateSuggestion $dateSuggestion)
    {
        $this->dateSuggestion = $dateSuggestion;
        $this->match = $dateSuggestion->match;
        $this->queue = 'notifications';
    }

    public function via($notifiable): array
    {
        $channels = ['database'];

        // Add push notification if user has enabled them
        if ($notifiable->notification_preferences['push_suggestions'] ?? true) {
            $channels[] = 'fcm';
        }

        return $channels;
    }

    public function toDatabase($notifiable): array
    {
        // Get the other user in the match
        $otherUser = $this->match->user1_id === $notifiable->id
            ? $this->match->user2
            : $this->match->user1;

        return [
            'type' => 'date_suggestion',
            'suggestion_id' => $this->dateSuggestion->id,
            'match_id' => $this->match->id,
            'other_user' => [
                'id' => $otherUser->id,
                'name' => $otherUser->first_name,
                'photo' => $otherUser->getPrimaryPhotoUrl(),
            ],
            'activity' => $this->dateSuggestion->activity_name,
            'day' => $this->dateSuggestion->day_name,
            'time' => $this->dateSuggestion->formatted_time,
            'created_at' => $this->dateSuggestion->created_at->toISOString(),
        ];
    }

    public function toFcm($notifiable): array
    {
        // Get the other user in the match
        $otherUser = $this->match->user1_id === $notifiable->id
            ? $this->match->user2
            : $this->match->user1;

        return [
            'title' => "Date Suggestion with {$otherUser->first_name}",
            'body' => "How about {$this->dateSuggestion->activity_name} on {$this->dateSuggestion->day_name}?",
            'data' => [
                'type' => 'date_suggestion',
                'suggestion_id' => $this->dateSuggestion->id,
                'match_id' => $this->match->id,
                'deep_link' => "violece://match/{$this->match->id}/suggestion/{$this->dateSuggestion->id}",
            ],
            'android' => [
                'channel_id' => 'date_suggestions',
                'priority' => 'high',
            ],
            'apns' => [
                'payload' => [
                    'aps' => [
                        'sound' => 'violece_suggestion.wav',
                        'badge' => $notifiable->unreadNotifications()->count() + 1,
                    ],
                ],
            ],
        ];
    }
}
