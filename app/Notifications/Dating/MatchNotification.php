<?php


namespace App\Notifications\Dating;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;
use App\Models\User;
use App\Models\Dating\UserMatch;

/**
 * Notification for new matches
 */
class MatchNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected User $matchedUser;
    protected UserMatch $match;

    public function __construct(User $matchedUser, UserMatch $match)
    {
        $this->matchedUser = $matchedUser;
        $this->match = $match;
        $this->queue = 'notifications';
    }

    public function via($notifiable): array
    {
        $channels = ['database']; // Always store in database

        // Add push notification
        if ($notifiable->notification_preferences['push_matches'] ?? true) {
            $channels[] = 'fcm';
        }

        // Add email notification
        if ($notifiable->notification_preferences['email_matches'] ?? true) {
            $channels[] = 'mail';
        }

        return $channels;
    }

    public function toDatabase($notifiable): array
    {
        return [
            'type' => 'match',
            'matched_user' => [
                'id' => $this->matchedUser->id,
                'name' => $this->matchedUser->first_name,
                'photo' => $this->matchedUser->getPrimaryPhotoUrl(),
                'age' => $this->matchedUser->date_of_birth?->age,
            ],
            'match_id' => $this->match->id,
            'compatibility_score' => round($this->match->compatibility_score ?? 0),
            'created_at' => $this->match->created_at->toISOString(),
        ];
    }

    public function toMail($notifiable): MailMessage
    {
        $compatibilityScore = round($this->match->compatibility_score ?? 0);

        return (new MailMessage)
            ->subject("🎉 You Have a New Match with {$this->matchedUser->first_name}!")
            ->greeting("Exciting news, {$notifiable->first_name}!")
            ->line("You and {$this->matchedUser->first_name} liked each other - it's a match!")
            ->when($compatibilityScore > 0, function ($message) use ($compatibilityScore) {
                return $message->line("Your compatibility score: {$compatibilityScore}%");
            })
            ->line("Now you can start a conversation and get to know each other better.")
            ->action('Start Chatting', url("/app/matches/{$this->match->id}"))
            ->line('The best connections start with authentic conversations.')
            ->salutation('Happy matching!')
            ->salutation('The Violece Team');
    }

    public function toFcm($notifiable): array
    {
        return [
            'title' => "🎉 It's a Match!",
            'body' => "You and {$this->matchedUser->first_name} liked each other. Start chatting now!",
            'data' => [
                'type' => 'match',
                'match_id' => $this->match->id,
                'matched_user_id' => $this->matchedUser->id,
                'deep_link' => "violece://matches/{$this->match->id}",
            ],
            'android' => [
                'channel_id' => 'matches',
                'priority' => 'high',
            ],
            'apns' => [
                'payload' => [
                    'aps' => [
                        'sound' => 'violece_match.wav',
                        'badge' => $notifiable->unreadNotifications()->count() + 1,
                    ],
                ],
            ],
        ];
    }
}
