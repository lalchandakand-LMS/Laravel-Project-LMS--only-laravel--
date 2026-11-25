<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentAdjustment extends Model
{
    protected $fillable = ['order_id', 'payment_id', 'created_by', 'type', 'amount', 'reference', 'reason'];

    protected $casts = ['amount' => 'float'];

    public function order() { return $this->belongsTo(Order::class); }
    public function payment() { return $this->belongsTo(Payment::class); }
    public function creator() { return $this->belongsTo(User::class, 'created_by'); }
}
