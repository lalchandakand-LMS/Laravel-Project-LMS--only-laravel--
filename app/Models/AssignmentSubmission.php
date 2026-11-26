<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AssignmentSubmission extends Model
{
    protected $fillable = [
        'assignment_id', 'student_id', 'answer_text', 'attachment', 'submitted_at',
        'status', 'score', 'feedback', 'graded_by', 'graded_at',
    ];

    protected $casts = [
        'submitted_at' => 'datetime',
        'graded_at' => 'datetime',
        'score' => 'float',
    ];

    public function assignment() { return $this->belongsTo(Assignment::class); }
    public function student() { return $this->belongsTo(User::class, 'student_id'); }
    public function grader() { return $this->belongsTo(User::class, 'graded_by'); }
}
