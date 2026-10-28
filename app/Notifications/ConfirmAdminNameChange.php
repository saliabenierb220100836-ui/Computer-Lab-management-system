<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ConfirmAdminNameChange extends Notification
{
    use Queueable;

    public function __construct(
        public readonly string $currentFirstName,
        public readonly string $currentLastName,
        public readonly string $newFirstName,
        public readonly string $newLastName,
        public readonly string $url,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Confirm your administrator name change')
            ->greeting('Hello '.$this->currentFirstName)
            ->line('A request was made to change the name on your administrator account.')
            ->line('Current name: '.$this->currentFirstName.' '.$this->currentLastName)
            ->line('Requested name: '.$this->newFirstName.' '.$this->newLastName)
            ->action('Confirm name change', $this->url)
            ->line('This confirmation link expires in 30 minutes. Your name will not change until you confirm.')
            ->line('If you did not request this change, you can ignore this email.');
    }
}
