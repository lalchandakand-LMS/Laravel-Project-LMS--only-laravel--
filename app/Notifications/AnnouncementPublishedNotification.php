<?php

namespace App\Notifications;

use App\Models\Announcement;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class AnnouncementPublishedNotification extends Notification
{
    use Queueable;

    public function __construct(public Announcement $announcement) {}
    public function via(object $notifiable): array { return ['database']; }
    public function toDatabase(object $notifiable): array
    {
        return [
            'title' => $this->announcement->title,
            'message' => 'A new notice has been published.',
            'url' => route('notices.show', $this->announcement),
            'type' => 'announcement',
            'announcement_id' => $this->announcement->id,
        ];
    }
}
