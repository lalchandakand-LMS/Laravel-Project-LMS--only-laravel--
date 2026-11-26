<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CoachingEnrollment extends Model
{
    protected $fillable = [
        'coaching_admission_id', 'student_id', 'coaching_batch_id',
        'course_id', 'status', 'admitted_at', 'ended_at',
    ];

    protected $casts = ['admitted_at' => 'datetime', 'ended_at' => 'datetime'];

    public function admission() { return $this->belongsTo(CoachingAdmission::class, 'coaching_admission_id'); }
    public function student() { return $this->belongsTo(User::class, 'student_id'); }
    public function batch() { return $this->belongsTo(CoachingBatch::class, 'coaching_batch_id'); }
    public function course() { return $this->belongsTo(Course::class); }
}
