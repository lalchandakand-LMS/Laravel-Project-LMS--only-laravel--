<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Concerns\TracksDeletionActor;

class Branch extends Model
{
    use TracksDeletionActor;

    public const PAYMENT_METHODS = [
        'bkash' => 'bKash',
        'nagad' => 'Nagad',
        'rocket' => 'Rocket',
        'bank' => 'Bank transfer',
        'cash' => 'Cash at office',
        'office' => 'Office payment',
    ];

    protected $fillable = ['name', 'code', 'address', 'phone', 'email', 'manager_id', 'is_active', 'payment_methods', 'bkash_number', 'nagad_number', 'rocket_number', 'bank_name', 'bank_branch', 'bank_account_name', 'bank_account_number', 'payment_instructions'];

    protected $casts = ['is_active' => 'boolean', 'payment_methods' => 'array'];

    public function manager()  { return $this->belongsTo(User::class, 'manager_id'); }
    public function orders() { return $this->hasMany(Order::class); }
    public function students() { return $this->hasMany(User::class)->where('role', 'student'); }
    public function users()    { return $this->hasMany(User::class); }

    public function scopeActive($q) { return $q->where('is_active', true); }

    public function enabledStudentPaymentMethods(): array
    {
        return array_values(array_intersect(['bkash', 'nagad', 'rocket', 'bank'], $this->payment_methods ?? []));
    }

    /**
     * Net money actually received for orders assigned to this branch.
     * Order ownership is immutable across student transfers.
     */
    public function revenue(): float
    {
        $branchOrders = fn ($query) => $query->where('branch_id', $this->id);

        $received = Payment::query()
            ->where('status', 'verified')
            ->whereHas('order', $branchOrders)
            ->sum('amount');

        $refunds = PaymentAdjustment::query()
            ->where('type', 'refund')
            ->whereHas('order', $branchOrders)
            ->sum('amount');

        $credits = PaymentAdjustment::query()
            ->where('type', 'credit')
            ->whereHas('order', $branchOrders)
            ->sum('amount');

        return max(0, round((float) $received - (float) $refunds + (float) $credits, 2));
    }

    public function studentCount(): int
    {
        return $this->users()->where('role', 'student')->count();
    }

    public function getRouteKeyName(): string
    {
        return 'code';
    }
}
