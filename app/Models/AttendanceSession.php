<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AttendanceSession extends Model
{
    protected $fillable = [
        'batch_id', 'course_id', 'branch_id', 'instructor_id', 'mode', 'topic',
        'starts_at', 'ends_at', 'status', 'submitted_at', 'reviewed_by',
        'reviewed_at', 'review_note',
    ];

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'submitted_at' => 'datetime',
        'reviewed_at' => 'datetime',
    ];

    public const MODES = ['online', 'offline', 'coaching'];
    public const STATUSES = ['draft', 'submitted', 'approved', 'returned'];

    public function batch() { return $this->belongsTo(Batch::class); }
    public function course() { return $this->belongsTo(Course::class); }
    public function branch() { return $this->belongsTo(Branch::class); }
    public function instructor() { return $this->belongsTo(User::class, 'instructor_id'); }
    public function reviewer() { return $this->belongsTo(User::class, 'reviewed_by'); }
    public function records() { return $this->hasMany(Attendance::class); }
}
