<?php

namespace App\\Models;

use Illuminate\\Database\\Eloquent\\Model;
use Illuminate\\Database\\Eloquent\\SoftDeletes;

class MarketingLead extends Model
{
    use SoftDeletes;

    public const STATUSES = ['new', 'contacted', 'qualified', 'converted', 'spam', 'closed'];

    protected $fillable = [
        'user_id', 'name', 'email', 'phone', 'subject', 'message', 'source',
        'utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content',
        'landing_page', 'referrer_host', 'status', 'admin_notes', 'assigned_to',
        'converted_user_id', 'converted_at',
    ];

    protected $casts = [
        'converted_at' => 'datetime',
    ];

    public function user() { return $this->belongsTo(User::class); }
    public function assignee() { return $this->belongsTo(User::class, 'assigned_to'); }
    public function convertedUser() { return $this->belongsTo(User::class, 'converted_user_id'); }
}
