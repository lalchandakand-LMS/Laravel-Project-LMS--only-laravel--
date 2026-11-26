<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductReturnRequest extends Model
{
    protected $fillable = [
        'order_id',
        'order_item_id',
        'user_id',
        'quantity',
        'reason',
        'evidence_path',
        'status',
        'admin_note',
        'reviewed_by',
        'reviewed_at',
        'received_by',
        'received_at',
        'restock_quantity',
        'restocked_at',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'restock_quantity' => 'integer',
        'reviewed_at' => 'datetime',
        'received_at' => 'datetime',
        'restocked_at' => 'datetime',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function item()
    {
        return $this->belongsTo(OrderItem::class, 'order_item_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function receiver()
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    public function statusBadge(): string
    {
        return match ($this->status) {
            'approved' => 'bg-sky-100 text-sky-700',
            'received' => 'bg-emerald-100 text-emerald-700',
            'rejected' => 'bg-rose-100 text-rose-700',
            default => 'bg-amber-100 text-amber-700',
        };
    }
}
