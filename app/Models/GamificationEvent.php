<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GamificationEvent extends Model
{
    protected $fillable = ['user_id', 'event_type', 'event_key', 'points', 'metadata'];

    protected $casts = ['metadata' => 'array'];

    public function user() { return $this->belongsTo(User::class); }
}
