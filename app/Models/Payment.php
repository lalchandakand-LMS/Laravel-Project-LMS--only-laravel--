<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    protected $fillable = [
        'order_id', 'gateway', 'transaction_id', 'provider_payment_id', 'sender_number', 'amount',
        'status', 'payload', 'proof_path', 'verified_by', 'verified_at', 'rejection_reason',
    ];

    protected $casts = [
        'payload' => 'array',
        'amount' => 'float',
        'verified_at' => 'datetime',
    ];

    public function order() { return $this->belongsTo(Order::class); }
    public function verifier() { return $this->belongsTo(User::class, 'verified_by'); }
}
