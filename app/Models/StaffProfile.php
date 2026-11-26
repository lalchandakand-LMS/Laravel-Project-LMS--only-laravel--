<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StaffProfile extends Model
{
    protected $fillable = [
        'user_id', 'employee_code', 'id_token', 'department', 'designation', 'employment_type',
        'joining_date', 'confirmation_date', 'experience_years',
        'highest_qualification', 'institution', 'employment_status', 'professional_bio', 'base_salary',
    ];

    protected function casts(): array
    {
        return [
            'joining_date' => 'date',
            'confirmation_date' => 'date',
            'experience_years' => 'integer',
            'base_salary' => 'decimal:2',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function documents()
    {
        return $this->morphMany(ProfileDocument::class, 'documentable')->latest();
    }

    public function payrolls()
    {
        return $this->hasMany(StaffPayroll::class)->latest('payroll_month');
    }

    public function attendances()
    {
        return $this->hasMany(StaffAttendance::class)->latest('attendance_date');
    }
}
