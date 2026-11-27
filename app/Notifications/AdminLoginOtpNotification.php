<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AdminLoginOtpNotification extends Notification
{
    use Queueable;

    public function __construct(public readonly string $code)
    {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Your administrator sign-in code')
            ->greeting('Administrator sign-in verification')
            ->line('Use this one-time code to complete your Alom’s English Center Admin sign-in:')
            ->line('**'.$this->code.'**')
            ->line('This code expires in 5 minutes and can be used only once.')
            ->line('If you did not request this code, secure your email account and contact the system owner.')
            ->salutation('Alom’s English Center');
    }
}
