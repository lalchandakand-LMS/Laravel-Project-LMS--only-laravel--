<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Complaint extends Model
{
    protected $fillable = ['tracking_code', 'user_id', 'branch_id', 'assigned_to', 'category', 'subject', 'body', 'priority', 'status', 'resolved_at', 'closed_at'];

    protected $casts = ['resolved_at' => 'datetime', 'closed_at' => 'datetime'];

    public function user() { return $this->belongsTo(User::class); }
    public function branch() { return $this->belongsTo(Branch::class); }
    public function assignee() { return $this->belongsTo(User::class, 'assigned_to'); }
    public function replies() { return $this->hasMany(ComplaintReply::class)->where('is_internal', false)->orderBy('created_at'); }
    public function allReplies() { return $this->hasMany(ComplaintReply::class)->orderBy('created_at'); }
    public function attachments() { return $this->hasMany(ComplaintAttachment::class)->whereNull('reply_id'); }
}
