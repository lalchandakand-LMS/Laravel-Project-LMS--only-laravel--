<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NewsletterCampaign extends Model
{
    public const STATUSES = ['draft', 'queued', 'sending', 'sent', 'completed_with_errors'];

    protected $fillable = [
        'created_by', 'subject', 'title', 'body', 'status',
        'recipients_count', 'sent_count', 'failed_count', 'skipped_count',
        'queued_at', 'completed_at',
    ];

    protected $casts = [
        'queued_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function deliveries()
    {
        return $this->hasMany(NewsletterCampaignDelivery::class);
    }
}
