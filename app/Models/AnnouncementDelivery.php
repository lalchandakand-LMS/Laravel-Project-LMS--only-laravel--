<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AnnouncementDelivery extends Model
{
    public $timestamps = false;
    protected $fillable = ['announcement_id', 'user_id', 'notified_at'];
    protected $casts = ['notified_at' => 'datetime'];

    public function announcement() { return $this->belongsTo(Announcement::class); }
    public function user() { return $this->belongsTo(User::class); }
}
