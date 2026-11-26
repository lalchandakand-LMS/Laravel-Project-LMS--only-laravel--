<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExamAttempt extends Model
{
    protected $fillable = ['exam_id','user_id','started_at','submitted_at','manual_reviewed_at','manual_feedback','score','max_score','pass_mark_target','duration_minutes','negative_marking','correct_count','wrong_count','status'];
    protected $casts = ['started_at' => 'datetime', 'submitted_at' => 'datetime', 'manual_reviewed_at' => 'datetime', 'max_score' => 'float', 'pass_mark_target' => 'float', 'duration_minutes' => 'integer', 'negative_marking' => 'float'];

    public function exam()    { return $this->belongsTo(Exam::class); }
    public function user()    { return $this->belongsTo(User::class); }
    public function answers() { return $this->hasMany(AttemptAnswer::class); }
    public function questions() { return $this->belongsToMany(Question::class, 'exam_attempt_questions')->withPivot('position')->orderByPivot('position'); }

    public function passMarksRequired(): float
    {
        $exam = $this->exam;
        if ($this->pass_mark_target !== null) {
            return (float) $this->pass_mark_target;
        }

        if ($this->max_score !== null && $exam->total_marks > 0) {
            return round(($exam->pass_marks / $exam->total_marks) * $this->max_score, 2);
        }

        return (float) $exam->pass_marks;
    }

    public function isPassed(): bool
    {
        return $this->score >= $this->passMarksRequired();
    }

    public function remainingSeconds(): int
    {
        $end = $this->started_at->copy()->addMinutes($this->duration_minutes ?? $this->exam->duration_minutes);
        return max(0, now()->diffInSeconds($end, false));
    }
}
