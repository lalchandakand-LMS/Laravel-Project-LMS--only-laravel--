<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StudentFeeCredit extends Model
{
    protected $fillable = ['student_id', 'payment_id', 'amount', 'note'];
    protected $casts = ['amount' => 'decimal:2'];

    public function student() { return $this->belongsTo(User::class, 'student_id'); }
    public function payment() { return $this->belongsTo(StudentFeePayment::class, 'payment_id'); }
    public function applications() { return $this->hasMany(StudentFeeCreditApplication::class, 'credit_id'); }
    public function remainingAmount(): float { return max(0, round((float) $this->amount - (float) $this->applications()->sum('amount'), 2)); }
}