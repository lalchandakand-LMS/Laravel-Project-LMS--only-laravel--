<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Enrollment extends Model
{
    protected $fillable = ['user_id','course_id','batch_id','branch_id','order_id','price_paid','status','enrolled_at','expires_at','completed_at','admin_note'];

    protected $casts = [
        'enrolled_at' => 'datetime',
        'expires_at' => 'datetime',
        'completed_at' => 'datetime',
        'price_paid' => 'decimal:2',
    ];

    public function user()   { return $this->belongsTo(User::class); }
    public function course() { return $this->belongsTo(Course::class); }
    public function order()  { return $this->belongsTo(Order::class); }
    public function batch() { return $this->belongsTo(Batch::class); }
    public function branch() { return $this->belongsTo(Branch::class); }
    public function histories() { return $this->hasMany(EnrollmentHistory::class)->latest(); }

    public function progressPercent(): int
    {
        return $this->course && $this->user ? $this->course->progressFor($this->user) : 0;
    }
}
