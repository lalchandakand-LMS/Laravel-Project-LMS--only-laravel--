<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentProfile extends Model
{
    protected $fillable = [
        'user_id', 'student_code', 'date_of_birth', 'gender', 'school_name',
        'academic_class', 'academic_roll', 'academic_session', 'academic_group',
        'present_address', 'permanent_address', 'emergency_contact_name',
        'emergency_contact_phone', 'status',
    ];

    protected function casts(): array
    {
        return ['date_of_birth' => 'date'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
