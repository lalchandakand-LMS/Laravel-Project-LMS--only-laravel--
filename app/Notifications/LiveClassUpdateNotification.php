<?php

namespace App\Notifications;

use App\Models\LiveClass;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class LiveClassUpdateNotification extends Notification
{
    use Queueable;

    public function __construct(
        public LiveClass $liveClass,
        public string $event = 'scheduled',
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        $message = match ($this->event) {
            'rescheduled' => 'The live class schedule or details have changed. Please check the updated information.',
            'cancelled' => 'This live class has been cancelled by the Admin or Teacher.',
            default => 'A new live class has been scheduled for your course.',
        };

        return [
            'title' => match ($this->event) {
                'rescheduled' => 'Live class updated: '.$this->liveClass->title,
                'cancelled' => 'Live class cancelled: '.$this->liveClass->title,
                default => 'New live class: '.$this->liveClass->title,
            },
            'message' => $message,
            'url' => route('student.live-classes'),
            'type' => 'live_class',
            'event' => $this->event,
            'live_class_id' => $this->liveClass->id,
            'starts_at' => $this->liveClass->starts_at?->toIso8601String(),
        ];
    }
}
