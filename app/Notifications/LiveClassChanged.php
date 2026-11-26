<?php

namespace App\Notifications;

use App\Models\LiveClass;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;
use App\Notifications\Channels\MiMSmsChannel;
use App\Notifications\Channels\QueuedMailChannel;

class LiveClassChanged extends Notification
{
    use Queueable;

    public function __construct(
        private readonly LiveClass $liveClass,
        private readonly string $event
    ) {}

    public function via(object $notifiable): array
    {
        $channels = ['database'];

        $smtp = (array) config('mail.mailers.smtp', []);
        $smtpReady = config('mail.default') === 'smtp'
            && filled($smtp['host'] ?? null)
            && filled($smtp['username'] ?? null)
            && filled($smtp['password'] ?? null)
            && filled(config('mail.from.address'));

        if (config('live_classes.email_notifications')
            && $smtpReady
            && filled($notifiable->routeNotificationFor('mail'))) {
            $channels[] = QueuedMailChannel::class;
        }

        if (config('live_classes.sms_notifications') && filled($notifiable->phone)) {
            $channels[] = MiMSmsChannel::class;
        }

        return $channels;
    }

    public function toMail(object $notifiable): MailMessage
    {
        $message = match ($this->event) {
            'cancelled' => "The live class \"{$this->liveClass->title}\" has been cancelled.",
            'rescheduled' => "The live class \"{$this->liveClass->title}\" has been rescheduled to ".$this->liveClass->starts_at?->format('d M Y, h:i A').'.',
            default => "A new live class \"{$this->liveClass->title}\" has been scheduled for ".$this->liveClass->starts_at?->format('d M Y, h:i A').'.',
        };

        $mail = (new MailMessage)
            ->subject('Live class update: '.$this->liveClass->title)
            ->greeting('Hello '.$notifiable->name.',')
            ->line($message);

        if ($this->event === 'cancelled' && $this->liveClass->cancellation_reason) {
            $mail->line('Reason: '.$this->liveClass->cancellation_reason);
        }

        return $mail
            ->action('View live class schedule', route('student.live-classes'))
            ->line('Please check your LMS schedule for the latest details.');
    }

    public function liveClass(): LiveClass
    {
        return $this->liveClass;
    }

    public function event(): string
    {
        return $this->event;
    }

    public function toDatabase(object $notifiable): array
    {
        $message = match ($this->event) {
            'cancelled' => "The live class \"{$this->liveClass->title}\" has been cancelled.",
            'rescheduled' => "The live class \"{$this->liveClass->title}\" has been rescheduled to ".$this->liveClass->starts_at?->format('d M Y, h:i A').'.',
            default => "A new live class \"{$this->liveClass->title}\" has been scheduled for ".$this->liveClass->starts_at?->format('d M Y, h:i A').'.',
        };

        return [
            'type' => 'live_class',
            'event' => $this->event,
            'title' => $this->liveClass->title,
            'message' => $message,
            'course_id' => $this->liveClass->course_id,
            'live_class_id' => $this->liveClass->id,
            'url' => route('student.live-classes'),
        ];
    }
}
