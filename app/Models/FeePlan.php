<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FeePlan extends Model
{
    protected $fillable = ['branch_id', 'course_id', 'name', 'type', 'billing_cycle', 'amount', 'due_day', 'is_active'];

    protected $casts = ['amount' => 'decimal:2', 'is_active' => 'boolean'];

    public function branch() { return $this->belongsTo(Branch::class); }
    public function course() { return $this->belongsTo(Course::class); }
    public function invoices() { return $this->hasMany(StudentFeeInvoice::class); }
}