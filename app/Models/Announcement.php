<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Concerns\TracksDeletionActor;

class Announcement extends Model
{
    use TracksDeletionActor;

    protected $fillable = ['course_id', 'batch_id', 'branch_id', 'title', 'body', 'is_published', 'priority', 'audience_role', 'publish_at', 'expires_at', 'is_pinned'];
    protected $casts = ['is_published' => 'boolean', 'is_pinned' => 'boolean', 'publish_at' => 'datetime', 'expires_at' => 'datetime'];

    public function course() { return $this->belongsTo(Course::class); }
    public function batch() { return $this->belongsTo(Batch::class); }
    public function branch() { return $this->belongsTo(Branch::class); }
    public function reads() { return $this->hasMany(AnnouncementRead::class); }
    public function attachments() { return $this->hasMany(AnnouncementAttachment::class); }
    public function deliveries() { return $this->hasMany(AnnouncementDelivery::class); }

    public function isVisible(): bool
    {
        return $this->is_published
            && (! $this->publish_at || $this->publish_at->isPast())
            && (! $this->expires_at || $this->expires_at->isFuture());
    }
}
