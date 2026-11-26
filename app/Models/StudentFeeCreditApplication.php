<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StudentFeeCreditApplication extends Model
{
    protected $fillable = ['credit_id', 'invoice_id', 'amount', 'applied_by'];
    protected $casts = ['amount' => 'decimal:2'];

    public function credit() { return $this->belongsTo(StudentFeeCredit::class, 'credit_id'); }
    public function invoice() { return $this->belongsTo(StudentFeeInvoice::class, 'invoice_id'); }
    public function appliedBy() { return $this->belongsTo(User::class, 'applied_by'); }
}