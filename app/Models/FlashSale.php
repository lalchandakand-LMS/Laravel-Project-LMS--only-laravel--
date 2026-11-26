<?php

namespace App\\Models;

use App\\Models\\Concerns\\TracksDeletionActor;
use Illuminate\\Database\\Eloquent\\Builder;
use Illuminate\\Database\\Eloquent\\Model;

class FlashSale extends Model
{
    use TracksDeletionActor;

    protected $fillable = ['name', 'type', 'value', 'description', 'starts_at', 'ends_at', 'is_active'];

    protected $casts = [
        'value' => 'float',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'is_active' => 'boolean',
    ];

    public function courses()
    {
        return $this->belongsToMany(Course::class, 'flash_sale_courses');
    }

    public function categories()
    {
        return $this->belongsToMany(Category::class, 'flash_sale_categories');
    }

    public function scopeCurrentlyRunning(Builder $query): Builder
    {
        return $query->where('is_active', true)
            ->where('starts_at', '<=', now())
            ->where('ends_at', '>', now());
    }

    public function discountedPrice(float $price): float
    {
        $discount = $this->type === 'percent'
            ? $price * ($this->value / 100)
            : $this->value;

        return round(max(0, $price - min($price, $discount)), 2);
    }

    public function statusLabel(): string
    {
        if (! $this->is_active) return 'Disabled';
        if (now()->lt($this->starts_at)) return 'Scheduled';
        if (now()->gte($this->ends_at)) return 'Ended';
        return 'Running';
    }
}
