<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\LessonProgress;
use App\Services\OrderService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class LearnController extends Controller
{
    private function guard(Course $course, ?Lesson $lesson = null): void
    {
        $user = auth()->user();

        if ($user->isAdmin()) {
            return;
        }

        abort_unless($user->isStudent(), 403, 'Student access only.');

        if ($lesson) {
            abort_unless($course->is_published && $lesson->is_published, 404);
            if ($lesson->is_free_preview) {
                return;
            }
        }

        $hasActiveEnrollment = $user->enrollments()
            ->where('course_id', $course->id)
            ->where('status', 'active')
            ->where(function ($query) {
                $query->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->exists();

        abort_unless($hasActiveEnrollment, 403, 'An active course enrollment is required.');
    }

    public function show(Course $course)
    {
        $this->guard($course);
        $lesson = $course->lessons()
            ->where('is_published', true)
            ->where(function ($query) {
                $query->where('is_free_preview', true)
                    ->orWhereHas('course.enrollments', function ($enrollments) {
                        $enrollments->where('user_id', auth()->id())
                            ->where('status', 'active')
                            ->where(function ($expiry) {
                                $expiry->whereNull('expires_at')->orWhere('expires_at', '>', now());
                            });
                    });
            })
            ->first();

        abort_if(! $lesson, 404, 'This course has no accessible lessons yet.');

        return redirect()->route('student.learn.lesson', [$course->slug, $lesson->id]);
    }

    public function lesson(Course $course, Lesson $lesson)
    {
        abort_unless((int) $lesson->course_id === (int) $course->id, 404);
        $this->guard($course, $lesson);

        $user = auth()->user();
        $course->load(['sections.lessons' => fn ($query) => $query->where('is_published', true), 'announcements', 'instructor']);
        $flat = $course->lessons()->where('is_published', true)->get();
        $index = $flat->search(fn ($item) => (int) $item->id === (int) $lesson->id);

        LessonProgress::firstOrCreate(['user_id' => $user->id, 'lesson_id' => $lesson->id]);

        return view('student.learn', [
            'course' => $course,
            'lesson' => $lesson,
            'prev' => $index !== false && $index > 0 ? $flat[$index - 1] : null,
            'next' => $index !== false && $index < $flat->count() - 1 ? $flat[$index + 1] : null,
            'completedIds' => LessonProgress::where('user_id', $user->id)
                ->whereNotNull('completed_at')->pluck('lesson_id')->all(),
            'progress' => $course->progressFor($user),
            'exams' => $lesson->exams()->where('is_published', true)->get(),
            'announcements' => $course->announcements()->where('is_published', true)->latest()->take(3)->get(),
        ]);
    }

    public function complete(Course $course, Lesson $lesson)
    {
        abort_unless((int) $lesson->course_id === (int) $course->id, 404);
        $this->guard($course, $lesson);
        $user = auth()->user();

        LessonProgress::updateOrCreate(
            ['user_id' => $user->id, 'lesson_id' => $lesson->id],
            ['completed_at' => now()]
        );

        $certificate = OrderService::maybeIssueCertificate($user->id, $course);
        $next = $course->lessons()->where('is_published', true)
            ->whereNotIn('id', LessonProgress::where('user_id', $user->id)
                ->whereNotNull('completed_at')->pluck('lesson_id'))
            ->orderBy('position')->first();

        $redirect = $next
            ? redirect()->route('student.learn.lesson', [$course->slug, $next->id])
            : redirect()->route('student.learn.lesson', [$course->slug, $lesson->id]);

        if ($course->progressFor($user) >= 100) {
            return $redirect->with('success', 'Congratulations, you finished the course! 🎉');
        }

        return $redirect->with('success', $certificate ? 'Lesson completed. Certificate unlocked!' : 'Lesson completed.');
    }

    public function heartbeat(Request $request, Course $course, Lesson $lesson)
    {
        abort_unless((int) $lesson->course_id === (int) $course->id, 404);
        $this->guard($course, $lesson);
        $data = $request->validate(['seconds' => 'required|integer|min:0|max:86400']);

        $progress = LessonProgress::firstOrCreate([
            'user_id' => auth()->id(),
            'lesson_id' => $lesson->id,
        ]);
        $progress->update(['watched_seconds' => max((int) $progress->watched_seconds, (int) $data['seconds'])]);

        return response()->json(['ok' => true]);
    }

    public function attachment(Course $course, Lesson $lesson)
    {
        abort_unless((int) $lesson->course_id === (int) $course->id, 404);
        $this->guard($course, $lesson);
        abort_unless($lesson->attachment && Storage::disk('local')->exists($lesson->attachment), 404);

        return Storage::disk('local')->download($lesson->attachment);
    }
}
