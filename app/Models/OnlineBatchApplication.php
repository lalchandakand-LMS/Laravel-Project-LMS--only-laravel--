<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OnlineBatchApplication extends Model
{
    protected $fillable = [
        'student_id', 'batch_id', 'course_id', 'status', 'student_note',
        'review_note', 'reviewed_by', 'reviewed_at', 'approved_at',
    ];

    protected $casts = ['reviewed_at' => 'datetime', 'approved_at' => 'datetime'];

    public function student() { return $this->belongsTo(User::class, 'student_id'); }
    public function batch() { return $this->belongsTo(Batch::class); }
    public function course() { return $this->belongsTo(Course::class); }
    public function reviewer() { return $this->belongsTo(User::class, 'reviewed_by'); }
}
