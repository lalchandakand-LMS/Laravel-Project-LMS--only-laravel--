<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Announcement extends Model
{
    use SoftDeletes;
    protected $fillable = ['course_id','title','body','is_published'];
    protected $casts = ['is_published' => 'boolean'];

    public function course() { return $this->belongsTo(Course::class); }
}
