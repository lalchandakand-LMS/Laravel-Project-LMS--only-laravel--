<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Section extends Model
{
    use SoftDeletes;
    protected $fillable = ['course_id','title','position'];

    public function course()  { return $this->belongsTo(Course::class); }
    public function lessons() { return $this->hasMany(Lesson::class)->orderBy('position'); }
}
