<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StudentFeeCredit extends Model
{
    protected $fillable = ['student_id', 'payment_id', 'amount', 'note'];
    protected $casts = ['amount' => 'decimal:2'];

    public function student() { return $this->belongsTo(User::class, 'student_id'); }
    public function payment() { return $this->belongsTo(StudentFeePayment::class, 'payment_id'); }
}