<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EnrollmentHistory extends Model
{
    protected $fillable = ['enrollment_id', 'actor_id', 'action', 'changes', 'ip_address'];

    protected $casts = ['changes' => 'array'];

    public function enrollment() { return $this->belongsTo(Enrollment::class); }
    public function actor() { return $this->belongsTo(User::class, 'actor_id'); }
}
