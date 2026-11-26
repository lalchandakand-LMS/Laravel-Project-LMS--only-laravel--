<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Concerns\TracksDeletionActor;

class Question extends Model
{
    use TracksDeletionActor;
    protected $fillable = ['exam_id','type','question','explanation','marks','position'];
    protected $casts = ['marks' => 'float'];

    public function exam()    { return $this->belongsTo(Exam::class); }
    public function options() { return $this->hasMany(Option::class)->orderBy('position'); }
    public function correctOption() { return $this->hasOne(Option::class)->where('is_correct', true); }
    public function answers() { return $this->hasMany(AttemptAnswer::class); }
}
