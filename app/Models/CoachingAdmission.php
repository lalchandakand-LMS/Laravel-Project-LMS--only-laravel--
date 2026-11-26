<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class CoachingAdmission extends Model
{
    protected $fillable = [
        'application_no', 'student_name', 'student_email', 'student_phone',
        'guardian_name', 'guardian_phone', 'guardian_email', 'branch_id',
        'course_id', 'batch_id', 'student_id', 'status', 'applicant_note',
        'admin_note', 'reviewed_by', 'reviewed_at', 'admitted_at',
    ];

    protected $casts = ['reviewed_at' => 'datetime', 'admitted_at' => 'datetime'];

    protected static function booted(): void
    {
        static::creating(function (self $admission) {
            $admission->application_no ??= 'AEC-'.now()->format('ymd').'-'.Str::upper(Str::random(6));
        });
    }

    public function student() { return $this->belongsTo(User::class, 'student_id'); }
    public function branch() { return $this->belongsTo(Branch::class); }
    public function course() { return $this->belongsTo(Course::class); }
    public function batch() { return $this->belongsTo(Batch::class); }
    public function reviewer() { return $this->belongsTo(User::class, 'reviewed_by'); }
    public function invoices() { return $this->hasMany(StudentFeeInvoice::class, 'student_id', 'student_id'); }

    public function isActive(): bool
    {
        return $this->status === 'approved' && $this->student_id !== null;
    }
}