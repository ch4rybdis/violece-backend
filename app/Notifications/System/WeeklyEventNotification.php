<?php


namespace App\Notifications\System;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * Notification for weekly events
 */
class WeeklyEventNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected $event;

    public function __construct($event)
    {
        $this->event = $event;
        $this->queue = 'notifications';
    }

    public function via($notifiable): array
    {
        $channels = ['database'];

        if ($notifiable->notification_preferences['push_events'] ?? true) {
            $channels[] = 'fcm';
        }

        if ($notifiable->notification_preferences['email_events'] ?? false) {
            $channels[] = 'mail';
        }

        return $channels;
    }

    public function toDatabase($notifiable): array
    {
        return [
            'type' => 'weekly_event',
            'event' => [
                'id' => $this->event->id,
                'title' => $this->event->title,
                'description' => $this->event->description,
                'starts_at' => $this->event->starts_at->toISOString(),
                'ends_at' => $this->event->ends_at->toISOString(),
            ],
        ];
    }

    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("🎯 New Weekly Event: {$this->event->title}")
            ->greeting("Hi {$notifiable->first_name}!")
            ->line("A new weekly matching event is starting: {$this->event->title}")
            ->line($this->event->description)
            ->line("Participate to meet people with similar interests and values.")
            ->action('Join Event', url("/app/events/{$this->event->id}"))
            ->line('Events are a great way to make meaningful connections.')
            ->salutation('Happy matching!')
            ->salutation('The Violece Team');
    }

    public function toFcm($notifiable): array
    {
        return [
            'title' => "🎯 New Event: {$this->event->title}",
            'body' => "Join now to meet compatible people!",
            'data' => [
                'type' => 'weekly_event',
                'event_id' => $this->event->id,
                'deep_link' => "violece://events/{$this->event->id}",
            ],
            'android' => [
                'channel_id' => 'events',
            ],
            'apns' => [
                'payload' => [
                    'aps' => [
                        'sound' => 'default',
                    ],
                ],
            ],
        ];
    }
}
