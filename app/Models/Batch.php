<?php

namespace App\Models;

use App\Models\Concerns\TracksDeletionActor;
use Illuminate\Database\Eloquent\Model;

class Batch extends Model
{
    use TracksDeletionActor;

    protected $fillable = [
        'course_id', 'level_id', 'branch_id', 'instructor_id', 'name', 'code',
        'description', 'starts_at', 'ends_at', 'schedule_days', 'schedule_time',
        'capacity', 'status',
    ];

    protected $casts = [
        'starts_at' => 'date',
        'ends_at' => 'date',
    ];

    public function course() { return $this->belongsTo(Course::class); }
    public function level() { return $this->belongsTo(Level::class); }
    public function branch() { return $this->belongsTo(Branch::class); }
    public function instructor() { return $this->belongsTo(User::class, 'instructor_id'); }
    public function enrollments() { return $this->hasMany(Enrollment::class); }
    public function onlineEnrollments() { return $this->hasMany(OnlineBatchEnrollment::class); }
    public function students() { return $this->hasManyThrough(User::class, Enrollment::class, 'batch_id', 'id', 'id', 'user_id'); }

    public function activeStudentCount(): int
    {
        $legacy = $this->enrollments()->where('status', 'active')->pluck('user_id');
        $online = $this->onlineEnrollments()->where('status', 'active')->pluck('student_id');

        return $legacy->merge($online)->unique()->count();
    }
}
