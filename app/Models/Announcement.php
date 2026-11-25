<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Concerns\TracksDeletionActor;

class Announcement extends Model
{
    use TracksDeletionActor;
    protected $fillable = ['course_id','batch_id','title','body','is_published','priority','audience_role','publish_at','expires_at','is_pinned'];
    protected $casts = ['is_published' => 'boolean', 'is_pinned' => 'boolean', 'publish_at' => 'datetime', 'expires_at' => 'datetime'];

    public function course() { return $this->belongsTo(Course::class); }
    public function batch() { return $this->belongsTo(Batch::class); }
    public function reads() { return $this->hasMany(AnnouncementRead::class); }
}
