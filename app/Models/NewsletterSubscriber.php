<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NewsletterSubscriber extends Model
{
    public const STATUSES = ['pending', 'subscribed', 'unsubscribed'];

    protected $fillable = [
        'email', 'name', 'status', 'confirmation_token_hash',
        'confirmed_at', 'unsubscribed_at', 'source',
    ];

    protected $hidden = ['confirmation_token_hash'];

    protected $casts = [
        'confirmed_at' => 'datetime',
        'unsubscribed_at' => 'datetime',
    ];
}
