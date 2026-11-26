<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CoachingBatch extends Model
{
    protected $fillable = [
        'branch_id', 'course_id', 'level_id', 'instructor_id', 'name', 'code',
        'description', 'admission_fee', 'monthly_fee', 'starts_at', 'ends_at',
        'schedule_days', 'schedule_time', 'capacity', 'status',
    ];

    protected $casts = [
        'admission_fee' => 'decimal:2',
        'monthly_fee' => 'decimal:2',
        'starts_at' => 'date',
        'ends_at' => 'date',
    ];

    public function branch() { return $this->belongsTo(Branch::class); }
    public function course() { return $this->belongsTo(Course::class); }
    public function level() { return $this->belongsTo(Level::class); }
    public function instructor() { return $this->belongsTo(User::class, 'instructor_id'); }
    public function admissions() { return $this->hasMany(CoachingAdmission::class); }
    public function enrollments() { return $this->hasMany(CoachingEnrollment::class); }

    public function activeStudentCount(): int
    {
        return $this->enrollments()->where('status', 'active')->count();
    }

    public function hasAvailableSeat(): bool
    {
        return ! $this->capacity || $this->activeStudentCount() < $this->capacity;
    }
}
