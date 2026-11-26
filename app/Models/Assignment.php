<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Assignment extends Model
{
    protected $fillable = [
        'course_id', 'lesson_id', 'title', 'description', 'instructions',
        'attachment', 'due_at', 'total_marks', 'is_published',
    ];

    protected $casts = [
        'due_at' => 'datetime',
        'total_marks' => 'float',
        'is_published' => 'boolean',
    ];

    public function course() { return $this->belongsTo(Course::class); }
    public function lesson() { return $this->belongsTo(Lesson::class); }
    public function submissions() { return $this->hasMany(AssignmentSubmission::class); }

    public function isClosed(): bool
    {
        return $this->due_at !== null && $this->due_at->isPast();
    }
}
