<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Concerns\TracksDeletionActor;

class Lesson extends Model
{
    use TracksDeletionActor;
    protected $fillable = [
        'course_id','section_id','title','type','video_url','attachment','content',
        'duration_minutes','position','is_free_preview','is_published','available_at','prerequisite_lesson_id',
    ];

    protected $casts = [
        'is_free_preview' => 'boolean',
        'is_published' => 'boolean',
        'available_at' => 'datetime',
    ];

    public function course()   { return $this->belongsTo(Course::class); }
    public function section()  { return $this->belongsTo(Section::class); }
    public function prerequisite() { return $this->belongsTo(self::class, 'prerequisite_lesson_id'); }
    public function dependents() { return $this->hasMany(self::class, 'prerequisite_lesson_id'); }
    public function progress() { return $this->hasMany(LessonProgress::class); }
    public function exams()    { return $this->hasMany(Exam::class); }

    /** Return an embed URL only for supported video providers or direct video files. */
    public function embedUrl(): ?string
    {
        $url = trim((string) $this->video_url);
        if ($url === '') {
            return null;
        }

        $parts = parse_url($url);
        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $host = strtolower((string) ($parts['host'] ?? ''));
        $path = (string) ($parts['path'] ?? '');

        if (! in_array($scheme, ['http', 'https'], true) || $host === '') {
            return null;
        }

        $youtubeHosts = ['youtube.com', 'www.youtube.com', 'm.youtube.com', 'youtu.be', 'youtube-nocookie.com', 'www.youtube-nocookie.com'];
        if (in_array($host, $youtubeHosts, true)) {
            $videoId = null;
            if ($host === 'youtu.be') {
                $videoId = trim($path, '/');
            } elseif (preg_match('~^/(?:embed|shorts|live)/([\\w-]{6,})~', $path, $match)) {
                $videoId = $match[1];
            } elseif ($path === '/watch') {
                parse_str((string) ($parts['query'] ?? ''), $query);
                $videoId = $query['v'] ?? null;
            }

            return is_string($videoId) && preg_match('/^[\\w-]{6,}$/', $videoId)
                ? 'https://www.youtube.com/embed/'.$videoId
                : null;
        }

        if (in_array($host, ['vimeo.com', 'www.vimeo.com', 'player.vimeo.com'], true)
            && preg_match('~/(?:video/)?(\\d+)$~', rtrim($path, '/'), $match)) {
            return 'https://player.vimeo.com/video/'.$match[1];
        }

        return preg_match('/\\.(mp4|webm|ogg)$/i', $path) ? $url : null;
    }

    public function isCompletedBy(?User $user): bool
    {
        if (! $user) {
            return false;
        }
        return $this->progress()->where('user_id', $user->id)->whereNotNull('completed_at')->exists();
    }
}
