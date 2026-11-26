<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Product extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'title', 'slug', 'sku', 'description', 'image', 'price',
        'stock_quantity', 'is_published',
    ];

    protected $casts = [
        'price' => 'float',
        'stock_quantity' => 'integer',
        'reserved_quantity' => 'integer',
        'is_published' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $product): void {
            $product->slug = static::uniqueSlug($product->slug ?: $product->title);
        });

        static::updating(function (self $product): void {
            if ($product->isDirty('slug') || ($product->isDirty('title') && blank($product->getOriginal('slug')))) {
                $product->slug = static::uniqueSlug($product->slug ?: $product->title, $product->getKey());
            }
        });
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('is_published', true)->whereColumn('stock_quantity', '>', 'reserved_quantity');
    }

    public function availableQuantity(): int
    {
        return max(0, (int) $this->stock_quantity - (int) $this->reserved_quantity);
    }

    public function imageUrl(): string
    {
        return $this->image ? media_url($this->image) : asset('images/aec-logo.svg');
    }

    private static function uniqueSlug(string $value, ?int $ignoreId = null): string
    {
        $base = Str::slug($value) ?: 'product';
        $slug = $base;
        $suffix = 2;

        while (static::withTrashed()->where('slug', $slug)
            ->when($ignoreId, fn (Builder $query) => $query->whereKeyNot($ignoreId))
            ->exists()) {
            $slug = $base.'-'.$suffix++;
        }

        return $slug;
    }
}
