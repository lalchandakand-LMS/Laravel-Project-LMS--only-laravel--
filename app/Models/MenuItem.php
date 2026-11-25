<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Concerns\TracksDeletionActor;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MenuItem extends Model
{
    use TracksDeletionActor;
    protected $fillable = [
        'label', 'url', 'location', 'parent_id', 'sort_order', 'icon',
        'open_new_tab', 'is_enabled', 'show_desktop', 'show_mobile',
    ];

    protected $casts = [
        'open_new_tab' => 'boolean',
        'is_enabled' => 'boolean',
        'show_desktop' => 'boolean',
        'show_mobile' => 'boolean',
    ];

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('sort_order');
    }

    public static function publicNavigation(string $location = 'header', string $device = 'desktop')
    {
        return static::query()
            ->where('location', $location)
            ->where('is_enabled', true)
            ->where($device === 'mobile' ? 'show_mobile' : 'show_desktop', true)
            ->whereNull('parent_id')
            ->with(['children' => fn ($query) => $query
                ->where('is_enabled', true)
                ->where($device === 'mobile' ? 'show_mobile' : 'show_desktop', true)])
            ->orderBy('sort_order')
            ->get();
    }
}
