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

        return [
            'title' => 'Attendance published: '.$status,
            'message' => 'Attendance for '.($this->session->topic ?: $this->session->batchName() ?: 'your class').' on '.$this->session->starts_at?->format('d M Y').' has been approved. Your status: '.$status.'.',
            'url' => $notifiable->role === 'guardian' ? route('guardian.dashboard') : route('student.attendance'),
            'type' => 'attendance',
            'attendance_session_id' => $this->session->id,
            'attendance_status' => $this->attendance->status,
        ];
    }
}
