<?php

namespace App\Notifications;

use App\Models\Message;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class NewMessageNotification extends Notification
{
    use Queueable;

    public function __construct(public Message $message) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'title' => 'New message',
            'message' => 'You received a message from '.$this->message->sender?->name.'.',
            'url' => route('messages.index'),
            'type' => 'message',
            'message_id' => $this->message->id,
        ];
    }
}
