<?php

namespace App\Http\Controllers;

use App\Models\Course;
use Illuminate\Http\Request;

class ReviewController extends Controller
{
    public function store(Request $request, Course $course)
    {
        $data = $request->validate([
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'comment' => ['nullable', 'string', 'max:1000'],
            'video_url' => ['nullable', 'string', 'max:500', function (string $attribute, mixed $value, \Closure $fail): void {
                if ($value === null || trim((string) $value) === '') return;
                $parts = parse_url(trim((string) $value));
                $host = strtolower((string) ($parts['host'] ?? ''));
                $scheme = strtolower((string) ($parts['scheme'] ?? ''));
                $allowedHosts = ['youtu.be', 'youtube.com', 'www.youtube.com', 'm.youtube.com', 'youtube-nocookie.com', 'www.youtube-nocookie.com', 'vimeo.com', 'www.vimeo.com', 'player.vimeo.com'];
                if ($scheme !== 'https' || ! in_array($host, $allowedHosts, true)) $fail('Use a secure YouTube or Vimeo link for a video review.');
            }],
        ]);

        $request->user()->reviews()->updateOrCreate(
            ['course_id' => $course->id],
            $data + ['is_approved' => false]
        );

        return back()->with('success', 'Thanks! Your review is pending approval.');
    }
}
