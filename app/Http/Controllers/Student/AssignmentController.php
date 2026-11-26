<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AssignmentController extends Controller
{
    private function canAccess(User $user, Assignment $assignment): bool
    {
        if (! $user->isStudent() || ! $assignment->is_published) {
            return false;
        }

        $course = $assignment->course;
        if (! $course || ! $course->is_published) {
            return false;
        }

        return \App\Services\EnrollmentEligibility::hasCourse($user, $course);
    }

    public function index()
    {
        $user = auth()->user();
        abort_unless($user->isStudent(), 403);

        $eligibleCourseIds = \App\Services\EnrollmentEligibility::query($user)->pluck('course_id');

        $assignments = Assignment::with(['course', 'submissions' => fn ($query) => $query->where('student_id', $user->id)])
            ->where('is_published', true)
            ->whereIn('course_id', $eligibleCourseIds)
            ->whereHas('course', fn ($course) => $course->where('is_published', true))
            ->orderBy('due_at')
            ->paginate(12);

        return view('student.assignments.index', compact('assignments'));
    }

    public function show(Assignment $assignment)
    {
        $user = auth()->user();
        abort_unless($this->canAccess($user, $assignment), 404);

        $assignment->load('course', 'lesson');
        $submission = $assignment->submissions()->where('student_id', $user->id)->first();

        return view('student.assignments.show', compact('assignment', 'submission'));
    }

    public function submit(Request $request, Assignment $assignment)
    {
        $user = $request->user();
        abort_unless($this->canAccess($user, $assignment), 404);

        $data = $request->validate([
            'answer_text' => 'nullable|string|max:20000',
            'attachment' => 'nullable|file|max:20480',
        ]);

        $previousAttachment = null;
        $newAttachment = null;

        try {
            DB::transaction(function () use ($assignment, $user, $request, $data, &$previousAttachment, &$newAttachment) {
                // Serialize attempts for this assignment so concurrent first submissions
                // or returned-work resubmissions cannot race the unique student row.
                $lockedAssignment = Assignment::with('course')->whereKey($assignment->id)->lockForUpdate()->firstOrFail();
                abort_unless($this->canAccess($user, $lockedAssignment), 404);
                abort_if($lockedAssignment->isClosed(), 403, 'The submission deadline has passed.');

                $submission = $lockedAssignment->submissions()
                    ->where('student_id', $user->id)
                    ->lockForUpdate()
                    ->first();

                abort_if($submission && $submission->status !== 'returned', 409,
                    'This assignment has already been submitted. You can edit it only after your teacher returns it.');

                if (blank($data['answer_text'] ?? null) && ! $request->hasFile('attachment') && ! $submission?->attachment) {
                    throw ValidationException::withMessages([
                        'answer_text' => 'Write an answer or attach a file before submitting.',
                    ]);
                }

                $previousAttachment = $submission?->attachment;
                $attachment = $previousAttachment;

                if ($request->hasFile('attachment')) {
                    $newAttachment = $request->file('attachment')->store('assignments/submissions', 'local');
                    if (! $newAttachment) {
                        throw ValidationException::withMessages([
                            'attachment' => 'The uploaded file could not be stored. Your previous submission file has been kept.',
                        ]);
                    }
                    $attachment = $newAttachment;
                }

                $values = [
                    'answer_text' => $data['answer_text'] ?? null,
                    'attachment' => $attachment,
                    'submitted_at' => now(),
                    'status' => 'submitted',
                    'score' => null,
                    'feedback' => null,
                    'graded_by' => null,
                    'graded_at' => null,
                ];

                if ($submission) {
                    $submission->update($values);
                } else {
                    $lockedAssignment->submissions()->create($values + ['student_id' => $user->id]);
                }
            });
        } catch (\Throwable $exception) {
            if ($newAttachment) {
                Storage::disk('local')->delete($newAttachment);
            }
            throw $exception;
        }

        if ($newAttachment && $previousAttachment && $previousAttachment !== $newAttachment) {
            Storage::disk('local')->delete($previousAttachment);
        }

        return redirect()->route('student.assignments.show', $assignment)
            ->with('success', 'Your assignment has been submitted successfully.');
    }

    public function downloadResource(Assignment $assignment)
    {
        $user = auth()->user();
        abort_unless($this->canAccess($user, $assignment), 404);
        abort_unless($assignment->attachment, 404);
        if ((str_starts_with(strtolower($assignment->attachment), 'http://') || str_starts_with(strtolower($assignment->attachment), 'https://'))) {
            return redirect()->away($assignment->attachment)->withHeaders([
                'Cache-Control' => 'private, no-store, max-age=0',
                'Referrer-Policy' => 'no-referrer',
            ]);
        }
        abort_unless(Storage::disk('local')->exists($assignment->attachment), 404);

        return response()->download(
            Storage::disk('local')->path($assignment->attachment),
            basename($assignment->attachment),
            ['Cache-Control' => 'private, no-store, max-age=0', 'X-Content-Type-Options' => 'nosniff']
        );
    }

    public function downloadSubmission(Assignment $assignment)
    {
        $user = auth()->user();
        abort_unless($this->canAccess($user, $assignment), 404);
        $submission = $assignment->submissions()->where('student_id', $user->id)->firstOrFail();
        abort_unless($submission->attachment && Storage::disk('local')->exists($submission->attachment), 404);

        return response()->download(
            Storage::disk('local')->path($submission->attachment),
            basename($submission->attachment),
            ['Cache-Control' => 'private, no-store, max-age=0', 'X-Content-Type-Options' => 'nosniff']
        );
    }
}
