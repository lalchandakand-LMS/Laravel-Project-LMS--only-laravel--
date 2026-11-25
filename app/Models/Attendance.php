<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Attendance extends Model
{
    protected $fillable = [
        'attendance_session_id', 'student_id', 'status', 'note', 'marked_by',
    ];

    public const STATUSES = ['present', 'absent', 'late', 'leave'];

    public function session() { return $this->belongsTo(AttendanceSession::class, 'attendance_session_id'); }
    public function student() { return $this->belongsTo(User::class, 'student_id'); }
    public function marker() { return $this->belongsTo(User::class, 'marked_by'); }
}
