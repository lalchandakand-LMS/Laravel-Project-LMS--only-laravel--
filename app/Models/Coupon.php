<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use App\Models\Concerns\TracksDeletionActor;

class Coupon extends Model
{
    use TracksDeletionActor;

    protected $fillable = [
        'code', 'type', 'value', 'min_amount', 'max_discount', 'branch_id',
        'course_ids', 'category_ids', 'ebook_ids', 'usage_limit', 'per_user_limit',
        'first_order_only', 'used_count', 'starts_at', 'expires_at', 'is_active',
    ];

    protected $casts = [
        'starts_at' => 'datetime',
        'expires_at' => 'datetime',
        'is_active' => 'boolean',
        'first_order_only' => 'boolean',
        'course_ids' => 'array',
        'category_ids' => 'array',
        'ebook_ids' => 'array',
        'max_discount' => 'float',
    ];

    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    /**
     * A coupon is reserved by a non-cancelled order at checkout. This prevents
     * concurrent checkouts from exceeding global or per-student usage limits.
     */
    public function reservedUsageCount(): int
    {
        return $this->orders()->whereNotIn('status', ['cancelled', 'failed', 'refunded'])->count();
    }

    public function reservedUsageCountFor(int $userId): int
    {
        return $this->orders()->where('user_id', $userId)
            ->whereNotIn('status', ['cancelled', 'failed', 'refunded'])->count();
    }

    public function eligibleSubtotal(array|Collection $items): float
    {
        $items = collect($items);
        $hasTargets = filled($this->course_ids) || filled($this->category_ids) || filled($this->ebook_ids);

        if (! $hasTargets) {
            return round((float) $items->sum('price'), 2);
        }

        $courseIds = collect($this->course_ids ?? [])->map(fn ($id) => (int) $id);
        $categoryIds = collect($this->category_ids ?? [])->map(fn ($id) => (int) $id);
        $ebookIds = collect($this->ebook_ids ?? [])->map(fn ($id) => (int) $id);

        $eligible = $items->filter(function (array $item) use ($courseIds, $categoryIds, $ebookIds) {
            if ($item['type'] === 'ebook') {
                return $ebookIds->contains((int) $item['id']);
            }

            if ($item['type'] !== 'course') {
                return false;
            }

            if ($courseIds->contains((int) $item['id'])) {
                return true;
            }

            if ($categoryIds->isEmpty()) {
                return false;
            }

            $course = Course::with('categories:id')->find($item['id']);
            if (! $course) {
                return false;
            }

            $ids = $course->categories->pluck('id')->map(fn ($id) => (int) $id)->all();
            if ($course->category_id) {
                $ids[] = (int) $course->category_id;
            }

            return count(array_intersect($ids, $categoryIds->all())) > 0;
        });

        return round((float) $eligible->sum('price'), 2);
    }

    public function isValidFor(
        float $amount,
        ?User $user = null,
        array|Collection $items = [],
        ?int $branchId = null
    ): bool {
        if (! $this->is_active || $amount < (float) $this->min_amount) {
            return false;
        }

        if ($this->starts_at && now()->lt($this->starts_at)) {
            return false;
        }

        if ($this->expires_at && now()->gt($this->expires_at)) {
            return false;
        }

        if ($this->branch_id && (int) $this->branch_id !== (int) $branchId) {
            return false;
        }

        if ($this->usage_limit > 0 && $this->reservedUsageCount() >= $this->usage_limit) {
            return false;
        }

        if ($user) {
            if ($this->per_user_limit > 0 && $this->reservedUsageCountFor($user->id) >= $this->per_user_limit) {
                return false;
            }

            if ($this->first_order_only && $user->orders()
                ->whereNotIn('status', ['cancelled', 'failed', 'refunded'])->exists()) {
                return false;
            }
        }

        if ((filled($this->course_ids) || filled($this->category_ids) || filled($this->ebook_ids))
            && $this->eligibleSubtotal($items) <= 0) {
            return false;
        }

        return true;
    }

    public function discountFor(float $amount): float
    {
        $discount = $this->type === 'percent'
            ? $amount * ((float) $this->value / 100)
            : (float) $this->value;

        if ($this->max_discount !== null && $this->max_discount > 0) {
            $discount = min($discount, (float) $this->max_discount);
        }

        return round(min($discount, $amount), 2);
    }
}
