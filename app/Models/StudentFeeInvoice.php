<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class StudentFeeInvoice extends Model
{
    protected $fillable = [
        'invoice_no', 'student_id', 'coaching_admission_id', 'coaching_batch_id',
        'fee_plan_id', 'course_id', 'branch_id', 'invoice_type', 'description',
        'issued_at', 'due_at', 'amount', 'discount', 'status', 'created_by',
    ];

    protected $casts = [
        'issued_at' => 'date', 'due_at' => 'date',
        'amount' => 'decimal:2', 'discount' => 'decimal:2',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $invoice) {
            $invoice->invoice_no ??= 'FEE'.now()->format('ymd').Str::upper(Str::random(6));
        });
    }

    public function student() { return $this->belongsTo(User::class, 'student_id'); }
    public function coachingAdmission() { return $this->belongsTo(CoachingAdmission::class, 'coaching_admission_id'); }
    public function coachingBatch() { return $this->belongsTo(CoachingBatch::class, 'coaching_batch_id'); }
    public function plan() { return $this->belongsTo(FeePlan::class, 'fee_plan_id'); }
    public function course() { return $this->belongsTo(Course::class); }
    public function branch() { return $this->belongsTo(Branch::class); }
    public function payments() { return $this->hasMany(StudentFeePayment::class, 'invoice_id'); }
    public function creditApplications() { return $this->hasMany(StudentFeeCreditApplication::class, 'invoice_id'); }

    public function paidAmount(): float
    {
        $payments = $this->relationLoaded('payments')
            ? $this->payments->sum('applied_amount')
            : $this->payments()->sum('applied_amount');
        $credits = $this->relationLoaded('creditApplications')
            ? $this->creditApplications->sum('amount')
            : $this->creditApplications()->sum('amount');

        return (float) $payments + (float) $credits;
    }

    public function dueAmount(): float
    {
        return max(0, round((float) $this->amount - (float) $this->discount - $this->paidAmount(), 2));
    }

    public function syncStatus(): void
    {
        $this->forceFill(['status' => $this->dueAmount() <= 0 ? 'paid' : ($this->paidAmount() > 0 ? 'partial' : 'unpaid')])->save();
    }
}
