<?php

namespace App\Notifications;

use App\Models\Complaint;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class ComplaintUpdateNotification extends Notification
{
    use Queueable;

    public function __construct(public Complaint $complaint, public string $event) {}
    public function via(object $notifiable): array { return ['database']; }
    public function toDatabase(object $notifiable): array
    {
        return [
            'title' => 'Complaint '.$this->complaint->tracking_code.' updated',
            'message' => $this->event,
            'url' => route('complaints.show', $this->complaint),
            'type' => 'complaint',
            'complaint_id' => $this->complaint->id,
        ];
    }
}
