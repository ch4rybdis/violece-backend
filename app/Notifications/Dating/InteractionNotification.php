<?php



namespace App\Notifications\Dating;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Messages\DatabaseMessage;
use App\Models\User;
use App\Models\Dating\UserInteraction;

/**
 * Notification for user interactions (likes, super likes)
 */
class InteractionNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected User $fromUser;
    protected UserInteraction $interaction;

    public function __construct(User $fromUser, UserInteraction $interaction)
    {
        $this->fromUser = $fromUser;
        $this->interaction = $interaction;
        $this->queue = 'notifications';
    }

    public function via($notifiable): array
    {
        $channels = ['database']; // Always store in database

        // Add push notification if user has enabled them
        if ($notifiable->notification_preferences['push_likes'] ?? true) {
            $channels[] = 'fcm'; // Firebase Cloud Messaging
        }

        // Add email for super likes only
        if ($this->interaction->interaction_type === 'super_like' &&
            ($notifiable->notification_preferences['email_super_likes'] ?? true)) {
            $channels[] = 'mail';
        }

        return $channels;
    }

    public function toDatabase($notifiable): array
    {
        return [
            'type' => 'interaction',
            'interaction_type' => $this->interaction->interaction_type,
            'from_user' => [
                'id' => $this->fromUser->id,
                'name' => $this->fromUser->first_name,
                'photo' => $this->fromUser->getPrimaryPhotoUrl(),
                'age' => $this->fromUser->date_of_birth?->age,
            ],
            'interaction_id' => $this->interaction->id,
            'message' => $this->interaction->message,
            'created_at' => $this->interaction->created_at->toISOString(),
        ];
    }

    public function toMail($notifiable): MailMessage
    {
        $message = new MailMessage();

        if ($this->interaction->interaction_type === 'super_like') {
            $message->subject("{$this->fromUser->first_name} Super Liked You on Violece!")
                ->greeting("Great news, {$notifiable->first_name}!")
                ->line("{$this->fromUser->first_name} sent you a Super Like with a personal message:")
                ->line('"' . $this->interaction->message . '"')
                ->action('View Profile', url("/app/profile/{$this->fromUser->id}"))
                ->line('Super Likes are special - they show someone is really interested in getting to know you.');
        } else {
            $message->subject("{$this->fromUser->first_name} Liked You on Violece!")
                ->greeting("Hi {$notifiable->first_name}!")
                ->line("{$this->fromUser->first_name} liked your profile on Violece.")
                ->action('View Profile', url("/app/profile/{$this->fromUser->id}"));
        }

        return $message->salutation('Happy matching!')
            ->salutation('The Violece Team');
    }

    public function toFcm($notifiable): array
    {
        $title = $this->interaction->interaction_type === 'super_like'
            ? "💖 {$this->fromUser->first_name} Super Liked You!"
            : "❤️ {$this->fromUser->first_name} Liked You!";

        $body = $this->interaction->interaction_type === 'super_like' && $this->interaction->message
            ? "\"{$this->interaction->message}\""
            : "Check out their profile and see if you're interested too!";

        return [
            'title' => $title,
            'body' => $body,
            'data' => [
                'type' => 'interaction',
                'interaction_type' => $this->interaction->interaction_type,
                'from_user_id' => $this->fromUser->id,
                'interaction_id' => $this->interaction->id,
                'deep_link' => "violece://profile/{$this->fromUser->id}",
            ],
            'android' => [
                'channel_id' => 'interactions',
                'priority' => 'high',
            ],
            'apns' => [
                'payload' => [
                    'aps' => [
                        'sound' => 'violece_like.wav',
                        'badge' => $notifiable->unreadNotifications()->count() + 1,
                    ],
                ],
            ],
        ];
    }
}
