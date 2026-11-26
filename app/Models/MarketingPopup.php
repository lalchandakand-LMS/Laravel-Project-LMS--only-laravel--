<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class MarketingPopup extends Model
{
    use SoftDeletes;

    public const STATUSES = ['draft', 'active', 'paused'];
    public const AUDIENCES = ['all', 'guest'];
    public const DISPLAY_OPTIONS = ['all_public_pages', 'homepage_only'];
    public const FREQUENCIES = ['once_per_session', 'every_page'];

    protected $fillable = [
        'created_by', 'title', 'body', 'image_url', 'cta_label', 'cta_url',
        'target_audience', 'display_on', 'frequency', 'priority',
        'starts_at', 'ends_at', 'status',
    ];

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'impressions' => 'integer',
        'clicks' => 'integer',
        'priority' => 'integer',
    ];

    public function creator() { return $this->belongsTo(User::class, 'created_by'); }

    public function scopeCurrentlyActive($query)
    {
        return $query->where('status', 'active')
            ->where(fn ($builder) => $builder->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
            ->where(fn ($builder) => $builder->whereNull('ends_at')->orWhere('ends_at', '>=', now()));
    }
}
