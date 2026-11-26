<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Order extends Model
{
    protected $fillable = ['order_no','user_id','branch_id','coupon_id','subtotal','discount','shipping_fee','total','status','name','phone','email','address','note','shipping_status','shipping_carrier','tracking_number','shipped_at','delivered_at'];

    protected $casts = ['subtotal' => 'float', 'discount' => 'float', 'shipping_fee' => 'float', 'total' => 'float', 'delivered_at' => 'datetime', 'shipped_at' => 'datetime'];

    protected static function booted(): void
    {
        static::creating(function (self $o) {
            $o->order_no ??= 'RM'.now()->format('ymd').Str::upper(Str::random(6));
        });
    }

    public function user()    { return $this->belongsTo(User::class); }
    public function branch()  { return $this->belongsTo(Branch::class); }
    public function items()   { return $this->hasMany(OrderItem::class); }
    public function payments(){ return $this->hasMany(Payment::class); }
    public function returnRequests() { return $this->hasMany(ProductReturnRequest::class); }
    public function adjustments(){ return $this->hasMany(PaymentAdjustment::class); }
    public function coupon()  { return $this->belongsTo(Coupon::class); }

    public function getRouteKeyName() { return 'order_no'; }

    public function amountPaid(): float
    {
        $received = (float) $this->payments()->where('status', 'verified')->sum('amount');
        $refunds = (float) $this->adjustments()->where('type', 'refund')->sum('amount');
        $credits = (float) $this->adjustments()->where('type', 'credit')->sum('amount');

        return max(0, round($received - $refunds + $credits, 2));
    }

    /** Cash received and still refundable; internal credit transfers are not cash receipts. */
    public function refundableAmount(): float
    {
        $received = (float) $this->payments()->where('status', 'verified')->sum('amount');
        $refunds = (float) $this->adjustments()->where('type', 'refund')->sum('amount');

        return max(0, round($received - $refunds, 2));
    }

    public function dueAmount(): float
    {
        return max(0, round($this->total - $this->amountPaid(), 2));
    }

    public function statusBadge(): string
    {
        return match ($this->status) {
            'paid' => 'bg-emerald-100 text-emerald-700',
            'pending', 'partially_paid' => 'bg-amber-100 text-amber-700',
            'failed', 'cancelled', 'refunded' => 'bg-rose-100 text-rose-700',
            default => 'bg-slate-100 text-slate-700',
        };
    }
}
