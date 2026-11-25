<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Concerns\TracksDeletionActor;

class Review extends Model
{
    use TracksDeletionActor;
    protected $fillable = ['course_id','user_id','rating','comment','is_approved'];
    protected $casts = ['is_approved' => 'boolean'];

    public function course() { return $this->belongsTo(Course::class); }
    public function user()   { return $this->belongsTo(User::class); }
}
