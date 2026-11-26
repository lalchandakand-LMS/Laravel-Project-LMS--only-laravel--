<?php

namespace App\Notifications;

use App\Models\Attendance;
use App\Models\AttendanceSession;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class AttendancePublishedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public AttendanceSession $session,
        public Attendance $attendance,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        $status = ucfirst($this->attendance->status);
        $className = $this->session->topic ?: $this->session->batchName() ?: 'your class';

        return [
            'type' => 'attendance',
            'title' => 'Attendance published: '.$status,
            'message' => 'Attendance for '.$className.' on '.$this->session->starts_at?->format('d M Y').' has been approved. Status: '.$status.'.',
            'attendance_session_id' => $this->session->id,
            'attendance_status' => $this->attendance->status,
            'url' => $notifiable->role === 'guardian'
                ? route('guardian.dashboard')
                : route('student.attendance'),
        ];
    }
}
