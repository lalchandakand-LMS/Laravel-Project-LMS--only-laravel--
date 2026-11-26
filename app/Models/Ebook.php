<?php

namespace App\Models;

use App\Models\Concerns\TracksDeletionActor;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Ebook extends Model
{
    use TracksDeletionActor;

    protected $fillable = ['title', 'slug', 'cover', 'file', 'description', 'price', 'pages', 'is_free', 'is_published'];
    protected $casts = ['is_free' => 'boolean', 'is_published' => 'boolean', 'price' => 'float'];

    protected static function booted(): void
    {
        static::saving(function (self $e) {
            if (! $e->slug) {
                $e->slug = Str::slug($e->title).'-'.Str::lower(Str::random(4));
            }
        });
    }

    public function getRouteKeyName()
    {
        return 'slug';
    }

    public function purchases()
    {
        return $this->hasMany(EbookPurchase::class);
    }

    public function flashSales()
    {
        return $this->belongsToMany(FlashSale::class, 'flash_sale_ebooks');
    }

    public function activeFlashSale(): ?FlashSale
    {
        if ($this->is_free || ! $this->is_published) {
            return null;
        }

        if ($this->relationLoaded('effectiveFlashSale')) {
            return $this->getRelation('effectiveFlashSale');
        }

        $regularPrice = (float) $this->price;
        $sale = FlashSale::currentlyRunning()
            ->whereHas('ebooks', fn ($query) => $query->where('ebooks.id', $this->id))
            ->get()
            ->filter(fn (FlashSale $candidate) => $candidate->discountedPrice($regularPrice) < $regularPrice)
            ->sortBy(fn (FlashSale $candidate) => $candidate->discountedPrice($regularPrice))
            ->first();

        $this->setRelation('effectiveFlashSale', $sale);

        return $sale;
    }

    public function getFinalPriceAttribute(): float
    {
        $sale = $this->activeFlashSale();

        return $this->is_free ? 0.0 : ($sale ? $sale->discountedPrice((float) $this->price) : (float) $this->price);
    }

    public function getHasDiscountAttribute(): bool
    {
        return ! $this->is_free && $this->final_price < (float) $this->price;
    }

    public function coverUrl(): string
    {
        return $this->cover ? media_url($this->cover) : 'https://picsum.photos/seed/book'.$this->id.'/400/560';
    }

    public function ownedBy(?User $user): bool
    {
        if (! $user) return false;

        if ($this->is_free) {
            return $this->is_published && ! $this->trashed();
        }

        return $this->purchases()->where('user_id', $user->id)->exists();
    }
}
