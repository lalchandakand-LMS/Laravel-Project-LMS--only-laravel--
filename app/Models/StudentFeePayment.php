<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class StudentFeePayment extends Model
{
    protected $fillable = ['receipt_no', 'invoice_id', 'student_id', 'amount', 'applied_amount', 'advance_amount', 'method', 'reference', 'note', 'collected_by', 'received_at'];

    protected $casts = ['amount' => 'decimal:2', 'applied_amount' => 'decimal:2', 'advance_amount' => 'decimal:2', 'received_at' => 'datetime'];

    protected static function booted(): void
    {
        static::creating(function (self $payment) {
            $payment->receipt_no ??= 'RCP'.now()->format('ymd').Str::upper(Str::random(6));
        });
    }

    public function invoice() { return $this->belongsTo(StudentFeeInvoice::class, 'invoice_id'); }
    public function student() { return $this->belongsTo(User::class, 'student_id'); }
    public function collector() { return $this->belongsTo(User::class, 'collected_by'); }
    public function credit() { return $this->hasOne(StudentFeeCredit::class, 'payment_id'); }
}