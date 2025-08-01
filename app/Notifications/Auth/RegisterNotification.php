<?php

namespace App\Notifications\Auth;

use Illuminate\Auth\Notifications\VerifyEmail as BaseVerifyEmail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * Custom email verification notification
 */
class RegisterNotification extends BaseVerifyEmail implements ShouldQueue
{
    use Queueable;

    public function __construct()
    {
        $this->queue = 'notifications';
        $this->delay = now()->addSeconds(5); // Small delay for better UX
    }

    public function toMail($notifiable): MailMessage
    {
        $verificationUrl = $this->verificationUrl($notifiable);

        return (new MailMessage)
            ->subject('Welcome to Violece - Verify Your Email')
            ->greeting("Welcome to Violece, {$notifiable->first_name}!")
            ->line('Thank you for joining Violece, where meaningful connections begin with understanding.')
            ->line('Please click the button below to verify your email address and complete your registration.')
            ->action('Verify Email Address', $verificationUrl)
            ->line('This verification link will expire in 60 minutes.')
            ->line('If you did not create this account, no further action is required.')
            ->salutation('Welcome to the future of dating,')
            ->salutation('The Violece Team');
    }

    public function via($notifiable): array
    {
        return ['mail'];
    }
}
