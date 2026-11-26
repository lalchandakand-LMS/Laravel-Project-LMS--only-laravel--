<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AttendanceSession extends Model
{
    protected $fillable = [
        'batch_id', 'live_class_id', 'coaching_batch_id', 'course_id', 'branch_id', 'instructor_id',
        'mode', 'topic', 'starts_at', 'ends_at', 'status', 'submitted_at',
        'reviewed_by', 'reviewed_at', 'review_note',
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
    public function liveClass() { return $this->belongsTo(LiveClass::class); }
    public function coachingBatch() { return $this->belongsTo(CoachingBatch::class, 'coaching_batch_id'); }
    public function course() { return $this->belongsTo(Course::class); }
    public function branch() { return $this->belongsTo(Branch::class); }
    public function instructor() { return $this->belongsTo(User::class, 'instructor_id'); }
    public function reviewer() { return $this->belongsTo(User::class, 'reviewed_by'); }
    public function records() { return $this->hasMany(Attendance::class); }

    public function isCoaching(): bool
    {
        return $this->coaching_batch_id !== null;
    }

    public function batchName(): ?string
    {
        return $this->coachingBatch?->name ?? $this->batch?->name;
    }
}
