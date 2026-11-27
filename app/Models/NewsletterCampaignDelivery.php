<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NewsletterCampaignDelivery extends Model
{
    protected $fillable = [
        'newsletter_campaign_id', 'newsletter_subscriber_id',
        'status', 'sent_at', 'failed_at',
    ];

    protected $casts = [
        'sent_at' => 'datetime',
        'failed_at' => 'datetime',
    ];

    public function campaign()
    {
        return $this->belongsTo(NewsletterCampaign::class, 'newsletter_campaign_id');
    }

    public function subscriber()
    {
        return $this->belongsTo(NewsletterSubscriber::class, 'newsletter_subscriber_id');
    }
}
