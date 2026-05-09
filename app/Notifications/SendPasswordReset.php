<?php

namespace Pterodactyl\Notifications;

use Illuminate\Bus\Queueable;

use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;

class SendPasswordReset extends Notification
{
    use Queueable;

    
    public function __construct(public string $token)
    {
    }

    
    public function via(): array
    {
        return ['mail'];
    }

    
    public function toMail(mixed $notifiable): MailMessage
    {
        return (new MailMessage())
            ->subject('Reset Password')
            ->line('You are receiving this email because we received a password reset request for your account.')
            ->action('Reset Password', url('/auth/password/reset/' . $this->token . '?email=' . urlencode($notifiable->email)))
            ->line('If you did not request a password reset, no further action is required.');
    }
}
