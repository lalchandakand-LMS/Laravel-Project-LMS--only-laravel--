<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Concerns\TracksDeletionActor;
use Illuminate\Support\Str;

class Ebook extends Model
{
    use TracksDeletionActor;
    protected $fillable = ['title','slug','cover','file','description','price','pages','is_free','is_published'];
    protected $casts = ['is_free' => 'boolean', 'is_published' => 'boolean', 'price' => 'float'];

    protected static function booted(): void
    {
        static::saving(function (self $e) {
            if (! $e->slug) $e->slug = Str::slug($e->title).'-'.Str::lower(Str::random(4));
        });
    }

    public function getRouteKeyName() { return 'slug'; }

    public function purchases() { return $this->hasMany(EbookPurchase::class); }

    public function coverUrl(): string
    {
        return $this->cover ? media_url($this->cover) : 'https://picsum.photos/seed/book'.$this->id.'/400/560';
    }

    public function ownedBy(?User $user): bool
    {
        if (! $user) return false;

        // Free access is a catalogue entitlement, not a permanent purchase.
        // Archived or unpublished free titles must not remain readable by everyone.
        if ($this->is_free) {
            return $this->is_published && ! $this->trashed();
        }

        return $this->purchases()->where('user_id', $user->id)->exists();
    }
}
