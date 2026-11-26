<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class CoachingAdmission extends Model
{
    protected $fillable = [
        'application_no', 'student_name', 'student_email', 'student_phone',
        'guardian_name', 'guardian_phone', 'guardian_email', 'student_sms_consent',
        'guardian_sms_consent', 'coaching_batch_id', 'branch_id', 'course_id',
        'student_id', 'status', 'applicant_note', 'admin_note', 'reviewed_by',
        'reviewed_at', 'admitted_at',
    ];

    protected $casts = [
        'reviewed_at' => 'datetime',
        'admitted_at' => 'datetime',
        'student_sms_consent' => 'boolean',
        'guardian_sms_consent' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $admission) {
            $admission->application_no ??= 'AEC-'.now()->format('ymd').'-'.Str::upper(Str::random(6));
        });
    }

    public function student() { return $this->belongsTo(User::class, 'student_id'); }
    public function branch() { return $this->belongsTo(Branch::class); }
    public function course() { return $this->belongsTo(Course::class); }
    public function coachingBatch() { return $this->belongsTo(CoachingBatch::class, 'coaching_batch_id'); }
    public function reviewer() { return $this->belongsTo(User::class, 'reviewed_by'); }
    public function invoices() { return $this->hasMany(StudentFeeInvoice::class, 'coaching_admission_id'); }
    public function enrollment() { return $this->hasOne(CoachingEnrollment::class, 'coaching_admission_id'); }

    public function isActive(): bool
    {
        return $this->status === 'admitted' && $this->student_id !== null;
    }

    public function activateAfterAdmissionFee(): CoachingEnrollment
    {
        return \Illuminate\Support\Facades\DB::transaction(function () {
            $admission = self::query()->lockForUpdate()->findOrFail($this->id);
            if ($admission->enrollment) {
                return $admission->enrollment;
            }

            $batch = CoachingBatch::query()->lockForUpdate()->findOrFail($admission->coaching_batch_id);
            abort_unless($batch->hasAvailableSeat(), 422, 'এই কোচিং ব্যাচের আসন পূর্ণ।');

            $enrollment = CoachingEnrollment::create([
                'coaching_admission_id' => $admission->id,
                'student_id' => $admission->student_id,
                'coaching_batch_id' => $batch->id,
                'course_id' => $admission->course_id,
                'status' => 'active',
                'admitted_at' => now(),
            ]);

            $admission->update(['status' => 'admitted', 'admitted_at' => now()]);

            return $enrollment;
        });
    }
}
