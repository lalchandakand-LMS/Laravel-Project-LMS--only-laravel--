<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Concerns\TracksDeletionActor;

class Review extends Model
{
    use TracksDeletionActor;
    protected $fillable = ['course_id','user_id','rating','comment','video_url','is_approved'];
    protected $casts = ['is_approved' => 'boolean'];

    public function course() { return $this->belongsTo(Course::class); }
    public function user()   { return $this->belongsTo(User::class); }

    /** A safe embed URL for student supplied YouTube or Vimeo review videos. */
    public function videoEmbedUrl(): ?string
    {
        $parts = parse_url(trim((string) $this->video_url));
        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $host = strtolower((string) ($parts['host'] ?? ''));
        $path = (string) ($parts['path'] ?? '');

        if ($scheme !== 'https') return null;

        if (in_array($host, ['youtu.be', 'youtube.com', 'www.youtube.com', 'm.youtube.com', 'youtube-nocookie.com', 'www.youtube-nocookie.com'], true)) {
            if ($host === 'youtu.be') $id = trim($path, '/');
            elseif (preg_match('~^/(?:embed|shorts|live)/([\w-]{6,})~', $path, $match)) $id = $match[1];
            elseif ($path === '/watch') { parse_str((string) ($parts['query'] ?? ''), $query); $id = $query['v'] ?? ''; }
            else $id = '';

            return is_string($id) && preg_match('/^[\w-]{6,}$/', $id) ? 'https://www.youtube-nocookie.com/embed/'.$id : null;
        }

        if (in_array($host, ['vimeo.com', 'www.vimeo.com', 'player.vimeo.com'], true)
            && preg_match('~/(?:video/)?(\d+)$~', rtrim($path, '/'), $match)) {
            return 'https://player.vimeo.com/video/'.$match[1];
        }

        return null;
    }
}
