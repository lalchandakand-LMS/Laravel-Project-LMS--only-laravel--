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
        'sslcommerz' => 'Online gateway (API-ready)',
    ];

    protected $fillable = ['name', 'code', 'address', 'phone', 'email', 'manager_id', 'is_active', 'payment_methods'];

    protected $casts = ['is_active' => 'boolean', 'payment_methods' => 'array'];

    public function manager()  { return $this->belongsTo(User::class, 'manager_id'); }
    public function students() { return $this->hasMany(User::class)->where('role', 'student'); }
    public function users()    { return $this->hasMany(User::class); }

    public function scopeActive($q) { return $q->where('is_active', true); }

    public function enabledStudentPaymentMethods(): array
    {
        return array_values(array_intersect(['bkash', 'nagad', 'rocket', 'bank', 'sslcommerz'], $this->payment_methods ?? []));
    }

    public function revenue(): float
    {
        return (float) Order::where('status', 'paid')
            ->whereHas('user', fn ($q) => $q->where('branch_id', $this->id))
            ->sum('total');
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
