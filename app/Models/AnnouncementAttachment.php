<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AnnouncementAttachment extends Model
{
    protected $fillable = ['announcement_id', 'uploaded_by', 'path', 'original_name', 'mime_type', 'size'];

    public function announcement() { return $this->belongsTo(Announcement::class); }
    public function uploader() { return $this->belongsTo(User::class, 'uploaded_by'); }
}
