<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class MarketingCampaign extends Model
{
    use SoftDeletes;

    public const CHANNELS = ['facebook', 'google', 'youtube', 'whatsapp', 'email', 'sms', 'offline', 'other'];
    public const STATUSES = ['draft', 'active', 'paused', 'completed'];

    protected $fillable = [
        'created_by', 'name', 'slug', 'channel', 'source', 'medium', 'term',
        'content', 'destination_url', 'budget', 'starts_at', 'ends_at', 'status',
    ];

    protected $casts = [
        'budget' => 'decimal:2',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $campaign) {
            if (! $campaign->slug) {
                $base = Str::slug($campaign->name) ?: 'campaign';
                $slug = $base;
                $suffix = 2;
                while (static::withTrashed()->where('slug', $slug)->exists()) {
                    $slug = $base.'-'.$suffix++;
                }
                $campaign->slug = $slug;
            }
        });
    }

    public function creator() { return $this->belongsTo(User::class, 'created_by'); }
    public function leads() { return $this->hasMany(MarketingLead::class, 'utm_campaign', 'slug'); }

    public function trackingUrl(): string
    {
        $destination = preg_match('/^https?:\/\//i', $this->destination_url)
            ? $this->destination_url
            : url($this->destination_url);

        $query = http_build_query(array_filter([
            'utm_source' => $this->source,
            'utm_medium' => $this->medium,
            'utm_campaign' => $this->slug,
            'utm_term' => $this->term,
            'utm_content' => $this->content,
        ], fn ($value) => $value !== null && $value !== ''));

        return $destination.(str_contains($destination, '?') ? '&' : '?').$query;
    }
}
