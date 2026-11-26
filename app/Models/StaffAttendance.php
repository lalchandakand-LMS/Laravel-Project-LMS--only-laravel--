<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StaffAttendance extends Model
{
    protected $fillable = [
        'staff_profile_id', 'branch_id', 'attendance_date', 'status',
        'check_in', 'check_out', 'notes', 'marked_by',
    ];

    protected function casts(): array
    {
        return ['attendance_date' => 'date'];
    }

    public function staffProfile(): BelongsTo
    {
        return $this->belongsTo(StaffProfile::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function marker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'marked_by');
    }
}
