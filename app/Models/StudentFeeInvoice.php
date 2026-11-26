<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class StudentFeeInvoice extends Model
{
    protected $fillable = ['invoice_no', 'student_id', 'coaching_admission_id', 'fee_plan_id', 'course_id', 'batch_id', 'branch_id', 'description', 'issued_at', 'due_at', 'amount', 'discount', 'status', 'created_by'];

    protected $casts = ['issued_at' => 'date', 'due_at' => 'date', 'amount' => 'decimal:2', 'discount' => 'decimal:2'];

    protected static function booted(): void
    {
        static::creating(function (self $invoice) {
            $invoice->invoice_no ??= 'FEE'.now()->format('ymd').Str::upper(Str::random(6));
        });
    }

    public function student() { return $this->belongsTo(User::class, 'student_id'); }
    public function coachingAdmission() { return $this->belongsTo(CoachingAdmission::class, 'coaching_admission_id'); }
    public function plan() { return $this->belongsTo(FeePlan::class, 'fee_plan_id'); }
    public function course() { return $this->belongsTo(Course::class); }
    public function batch() { return $this->belongsTo(Batch::class); }
    public function branch() { return $this->belongsTo(Branch::class); }
    public function payments() { return $this->hasMany(StudentFeePayment::class, 'invoice_id'); }

    public function paidAmount(): float
    {
        return (float) $this->payments()->sum('applied_amount');
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