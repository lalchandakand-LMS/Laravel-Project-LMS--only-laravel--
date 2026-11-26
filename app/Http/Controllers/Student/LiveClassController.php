<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\LiveClass;

class LiveClassController extends Controller
{
    public function index()
    {
        $user = auth()->user();
        abort_unless($user->isStudent() || $user->isAdmin(), 403);

        $query = LiveClass::query()
            ->with('course')
            ->where('is_published', true)
            ->whereNotNull('starts_at')
            ->whereHas('course', fn ($course) => $course->where('is_published', true));

        if (! $user->isAdmin()) {
            $eligibleCourseIds = \App\Services\EnrollmentEligibility::query($user)->pluck('course_id');
            $query->whereIn('course_id', $eligibleCourseIds);
        }

        $classes = $query
            ->orderByRaw('CASE WHEN starts_at >= ? THEN 0 ELSE 1 END', [now()])
            ->orderByRaw('CASE WHEN starts_at >= ? THEN starts_at END ASC', [now()])
            ->orderByRaw('CASE WHEN starts_at < ? THEN starts_at END DESC', [now()])
            ->paginate(12);

        return view('student.live-classes', compact('classes'));
    }
}
