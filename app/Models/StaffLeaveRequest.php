<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StaffLeaveRequest extends Model
{
    protected $fillable = [
        'user_id', 'start_date', 'end_date', 'leave_type', 'reason',
        'status', 'reviewed_by', 'reviewed_at', 'reviewer_note',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'reviewed_at' => 'datetime',
    ];

    public const TYPES = [
        'annual' => 'Annual leave',
        'sick' => 'Sick leave',
        'personal' => 'Personal leave',
        'emergency' => 'Emergency leave',
        'other' => 'Other',
    ];

    public function user() { return $this->belongsTo(User::class); }
    public function reviewer() { return $this->belongsTo(User::class, 'reviewed_by'); }
}
