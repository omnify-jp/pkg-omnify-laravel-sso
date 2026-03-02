<?php

namespace Omnify\Core\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class WelcomeUserNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly string $organizationName,
        private readonly string $resetUrl,
    ) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Welcome to {$this->organizationName}")
            ->line('Your account has been created by an administrator.')
            ->line('Please click the button below to set your password and get started.')
            ->action('Set Password', $this->resetUrl)
            ->line('This link will expire in 60 minutes.');
    }
}
