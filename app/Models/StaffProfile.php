<?php

namespace App\\Models;

use Illuminate\\Database\\Eloquent\\Model;
use Illuminate\\Database\\Eloquent\\Relations\\BelongsTo;

class StaffProfile extends Model
{
    protected $fillable = [
        'user_id', 'employee_code', 'department', 'designation', 'employment_type',
        'joining_date', 'confirmation_date', 'experience_years',
        'highest_qualification', 'institution', 'employment_status', 'professional_bio',
    ];

    protected function casts(): array
    {
        return [
            'joining_date' => 'date',
            'confirmation_date' => 'date',
            'experience_years' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
