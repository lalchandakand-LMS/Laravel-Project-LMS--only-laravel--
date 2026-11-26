<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OnlineBatchEnrollment extends Model
{
    protected $fillable = ['application_id', 'student_id', 'batch_id', 'course_id', 'status', 'admitted_at', 'ended_at'];

    protected $casts = ['admitted_at' => 'datetime', 'ended_at' => 'datetime'];

    public function application() { return $this->belongsTo(OnlineBatchApplication::class, 'application_id'); }
    public function student() { return $this->belongsTo(User::class, 'student_id'); }
    public function batch() { return $this->belongsTo(Batch::class); }
    public function course() { return $this->belongsTo(Course::class); }
}
