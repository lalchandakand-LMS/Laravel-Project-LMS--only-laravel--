<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Concerns\TracksDeletionActor;

class Announcement extends Model
{
    use TracksDeletionActor;
    protected $fillable = ['course_id','title','body','is_published'];
    protected $casts = ['is_published' => 'boolean'];

    public function course() { return $this->belongsTo(Course::class); }
}
