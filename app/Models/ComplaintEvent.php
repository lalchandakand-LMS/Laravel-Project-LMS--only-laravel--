<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ComplaintEvent extends Model
{
    protected $fillable = ['complaint_id', 'created_by', 'event_type', 'description', 'is_internal'];
    protected $casts = ['is_internal' => 'boolean'];

    public function complaint() { return $this->belongsTo(Complaint::class); }
    public function actor() { return $this->belongsTo(User::class, 'created_by'); }
}
