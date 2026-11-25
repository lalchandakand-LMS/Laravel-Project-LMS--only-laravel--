<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ComplaintReply extends Model
{
    protected $fillable = ['complaint_id', 'user_id', 'body', 'is_internal'];
    protected $casts = ['is_internal' => 'boolean'];
    public function complaint() { return $this->belongsTo(Complaint::class); }
    public function user() { return $this->belongsTo(User::class); }
    public function attachments() { return $this->hasMany(ComplaintAttachment::class, 'reply_id'); }
}
