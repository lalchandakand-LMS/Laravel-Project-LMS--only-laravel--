<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Concerns\TracksDeletionActor;

class LiveClass extends Model
{
    use TracksDeletionActor;
    protected $fillable = [
        'course_id', 'batch_id', 'title', 'description', 'meeting_url', 'meeting_password',
        'recording_url', 'platform', 'starts_at', 'duration_minutes', 'is_published', 'cancelled_at', 'cancellation_reason',
    ];

    protected $casts = ['starts_at' => 'datetime', 'is_published' => 'boolean', 'cancelled_at' => 'datetime'];

    public const PLATFORMS = ['Zoom', 'Google Meet', 'Jitsi', 'YouTube Live', 'Facebook Live', 'Offline / Classroom'];

    public function course() { return $this->belongsTo(Course::class); }
    public function batch() { return $this->belongsTo(Batch::class); }
    public function attendanceSession() { return $this->hasOne(AttendanceSession::class); }

    public function scopePublished($q) { return $q->where('is_published', true); }

    public function endsAt() { return $this->starts_at?->copy()->addMinutes((int) $this->duration_minutes); }

    public function isUpcoming(): bool { return $this->starts_at && $this->starts_at->isFuture(); }

    public function isCancelled(): bool { return $this->cancelled_at !== null; }

    public function isLive(): bool
    {
        return ! $this->isCancelled() && $this->starts_at
            && now()->between($this->starts_at, $this->endsAt() ?? $this->starts_at);
    }

    /** Join button only unlocks 10 minutes before the class starts. */
    public function canJoin(): bool
    {
        return ! $this->isCancelled() && $this->starts_at && now()->gte($this->starts_at->copy()->subMinutes(10))
            && now()->lte(($this->endsAt() ?? $this->starts_at)->copy()->addMinutes(30));
    }

    public function statusLabel(): string
    {
        return match (true) {
            $this->isCancelled() => 'Cancelled',
            $this->isLive() => 'Live now',
            $this->isUpcoming() => 'Upcoming',
            $this->recording_url !== null => 'Recording ready',
            default => 'Finished',
        };
    }

    public function statusBadge(): string
    {
        return match ($this->statusLabel()) {
            'Cancelled' => 'bg-red-100 text-red-700',
            'Live now' => 'bg-rose-100 text-rose-700',
            'Upcoming' => 'bg-emerald-100 text-emerald-700',
            'Recording ready' => 'bg-indigo-100 text-indigo-700',
            default => 'bg-slate-100 text-slate-600',
        };
    }
}
