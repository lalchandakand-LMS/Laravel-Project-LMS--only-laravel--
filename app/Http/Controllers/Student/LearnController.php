<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\LessonProgress;
use App\Models\User;
use App\Services\OrderService;
use App\Services\GamificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class LearnController extends Controller
{
    /** Return a currently valid enrollment, including batch and branch restrictions. */
    private function activeEnrollment(User $user, Course $course)
    {
        return \App\Services\EnrollmentEligibility::forCourse($user, $course);
    }

    /** Lessons visible to this learner at this moment (published, released and prerequisite-unlocked). */
    private function accessibleLessons(Course $course, User $user)
    {
        $query = $course->lessons()->where('is_published', true);

        if ($user->isAdmin()) {
            return $query;
        }

        $query->where(fn ($available) => $available->whereNull('available_at')->orWhere('available_at', '<=', now()))
            ->where(fn ($prerequisite) => $prerequisite->whereNull('prerequisite_lesson_id')
                ->orWhereExists(function ($completed) use ($user) {
                    $completed->selectRaw('1')->from('lesson_progress')
                        ->whereColumn('lesson_progress.lesson_id', 'lessons.prerequisite_lesson_id')
                        ->where('lesson_progress.user_id', $user->id)
                        ->whereNotNull('lesson_progress.completed_at');
                }));

        if (! $this->activeEnrollment($user, $course)) {
            $query->where('is_free_preview', true);
        }

        return $query;
    }

    private function guard(Course $course, ?Lesson $lesson = null): void
    {
        $user = auth()->user();

        if ($user->isAdmin()) {
            return;
        }

        abort_unless($user->isStudent(), 403, 'Student access only.');

        if ($lesson) {
            abort_unless($course->is_published && $lesson->is_published, 404);
            abort_if($lesson->available_at && $lesson->available_at->isFuture(), 403, 'This lesson will unlock on its scheduled release date.');

            if ($lesson->prerequisite_lesson_id) {
                $completed = LessonProgress::where('user_id', $user->id)
                    ->where('lesson_id', $lesson->prerequisite_lesson_id)
                    ->whereNotNull('completed_at')->exists();
                abort_unless($completed, 403, 'Complete the prerequisite lesson to unlock this lesson.');
            }

            if ($lesson->is_free_preview) {
                return;
            }
        }

        abort_unless($this->activeEnrollment($user, $course), 403,
            'An active, unexpired course enrollment and eligible batch are required.');
    }

    public function show(Course $course)
    {
        $user = auth()->user();
        abort_unless($user->isAdmin() || ($user->isStudent() && $course->is_published), 403);

        $hasPreview = $course->lessons()->where('is_published', true)->where('is_free_preview', true)->exists();
        abort_unless($user->isAdmin() || $this->activeEnrollment($user, $course) || $hasPreview, 403,
            'An active course enrollment is required.');

        $lesson = $this->accessibleLessons($course, $user)->orderBy('position')->first();
        abort_if(! $lesson, 404, 'There are no lessons available to you yet. Check the release schedule or your enrollment.');

        return redirect()->route('student.learn.lesson', [$course->slug, $lesson->id]);
    }

    public function lesson(Course $course, Lesson $lesson)
    {
        abort_unless((int) $lesson->course_id === (int) $course->id, 404);
        $this->guard($course, $lesson);

        $user = auth()->user();
        $flat = $this->accessibleLessons($course, $user)->orderBy('position')->get();
        $index = $flat->search(fn ($item) => (int) $item->id === (int) $lesson->id);

        // The sidebar is part of the learning interface: only expose lessons the
        // current learner can actually open, not merely every published lesson.
        $accessibleLessonIds = $flat->pluck('id')->all();
        $course->load([
            'sections.lessons' => fn ($query) => $query
                ->where('is_published', true)
                ->whereIn('id', $accessibleLessonIds),
            'announcements',
            'instructor',
        ]);

        $lessonProgress = LessonProgress::firstOrCreate([
            'user_id' => $user->id,
            'lesson_id' => $lesson->id,
        ]);

        return view('student.learn', [
            'course' => $course,
            'lesson' => $lesson,
            'prev' => $index !== false && $index > 0 ? $flat[$index - 1] : null,
            'next' => $index !== false && $index < $flat->count() - 1 ? $flat[$index + 1] : null,
            'completedIds' => LessonProgress::where('user_id', $user->id)
                ->whereNotNull('completed_at')->pluck('lesson_id')->all(),
            'progress' => $course->progressFor($user),
            'watchedSeconds' => (int) $lessonProgress->watched_seconds,
            'exams' => $lesson->exams()->where('is_published', true)->get(),
            'announcements' => $course->announcements()->where('is_published', true)->latest()->take(3)->get(),
        ]);
    }

    public function complete(Course $course, Lesson $lesson)
    {
        abort_unless((int) $lesson->course_id === (int) $course->id, 404);
        $this->guard($course, $lesson);
        $user = auth()->user();

        $progress = LessonProgress::firstOrNew([
            'user_id' => $user->id,
            'lesson_id' => $lesson->id,
        ]);
        $wasCompleted = $progress->exists && $progress->completed_at !== null;

        if (! $wasCompleted) {
            $progress->completed_at = now();
            $progress->save();
        }

        $hasActiveEnrollment = $user->isAdmin() || (bool) $this->activeEnrollment($user, $course);
        if ($user->isStudent() && $hasActiveEnrollment && ! $wasCompleted) {
            GamificationService::award(
                $user,
                'lesson_completed',
                'lesson:'.$lesson->id,
                10,
                ['course_id' => $course->id, 'lesson_id' => $lesson->id]
            );
        }
        $certificate = $hasActiveEnrollment
            ? OrderService::maybeIssueCertificate($user->id, $course)
            : null;

        $completedIds = LessonProgress::where('user_id', $user->id)
            ->whereNotNull('completed_at')->pluck('lesson_id');
        $next = $this->accessibleLessons($course, $user)
            ->whereNotIn('id', $completedIds)
            ->orderBy('position')->first();

        $redirect = $next
            ? redirect()->route('student.learn.lesson', [$course->slug, $next->id])
            : redirect()->route('student.learn.lesson', [$course->slug, $lesson->id]);

        if ($hasActiveEnrollment && $course->progressFor($user) >= 100) {
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

        // Keep the furthest playback position so seeking backwards never loses a saved resume point.
        $progress->update([
            'watched_seconds' => max((int) $progress->watched_seconds, (int) $data['seconds']),
        ]);

        return response()->json(['ok' => true, 'watched_seconds' => (int) $progress->fresh()->watched_seconds]);
    }

    public function attachment(Course $course, Lesson $lesson)
    {
        abort_unless((int) $lesson->course_id === (int) $course->id, 404);
        $this->guard($course, $lesson);
        abort_unless($lesson->attachment, 404);
        if (str_starts_with($lesson->attachment, 'https://')) {
            return redirect()->away($lesson->attachment);
        }
        abort_unless(Storage::disk('local')->exists($lesson->attachment), 404);

        return response()->download(
            Storage::disk('local')->path($lesson->attachment),
            basename($lesson->attachment),
            ['Cache-Control' => 'private, no-store, max-age=0', 'X-Content-Type-Options' => 'nosniff']
        );
    }
}
