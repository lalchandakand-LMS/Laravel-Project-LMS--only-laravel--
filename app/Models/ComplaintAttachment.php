<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ComplaintAttachment extends Model
{
    protected $fillable = ['complaint_id', 'reply_id', 'uploaded_by', 'path', 'original_name', 'mime_type', 'size'];
    public function complaint() { return $this->belongsTo(Complaint::class); }
    public function reply() { return $this->belongsTo(ComplaintReply::class, 'reply_id'); }
    public function uploader() { return $this->belongsTo(User::class, 'uploaded_by'); }
}
