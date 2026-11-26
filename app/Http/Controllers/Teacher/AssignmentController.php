<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\Course;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class AssignmentController extends Controller
{
    private function courses()
    {
        $user = auth()->user();

        return Course::query()
            ->when(! $user->isAdmin(), fn ($query) => $query->where('instructor_id', $user->id))
            ->orderBy('title')->get();
    }

    private function authorizeCourse(Course $course): void
    {
        $user = auth()->user();
        abort_unless($user->isAdmin() || (int) $course->instructor_id === (int) $user->id, 403);
    }

    private function authorizeAssignment(Assignment $assignment): void
    {
        abort_unless($assignment->course, 404);
        $this->authorizeCourse($assignment->course);
    }

    public function index()
    {
        $user = auth()->user();
        $assignments = Assignment::with('course')->withCount('submissions')
            ->whereHas('course', fn ($query) => $query->when(
                ! $user->isAdmin(),
                fn ($owned) => $owned->where('instructor_id', $user->id)
            ))
            ->latest()->paginate(15);

        return view('teacher.assignments.index', compact('assignments'));
    }

    public function create()
    {
        return view('teacher.assignments.form', [
            'assignment' => new Assignment(['total_marks' => 100]),
            'courses' => $this->courses(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $course = Course::findOrFail($data['course_id']);
        $this->authorizeCourse($course);
        $this->assertLessonBelongsToCourse($data);

        if ($request->hasFile('attachment')) {
            $data['attachment'] = $request->file('attachment')->store('assignments/resources', 'local');
        } elseif ($request->filled('resource_url')) {
            $data['attachment'] = $data['resource_url'];
        }
        unset($data['resource_url']);

        Assignment::create($data);

        return redirect()->route('teacher.assignments.index')->with('success', 'Assignment created.');
    }

    public function edit(Assignment $assignment)
    {
        $this->authorizeAssignment($assignment);

        return view('teacher.assignments.form', [
            'assignment' => $assignment,
            'courses' => $this->courses(),
        ]);
    }

    public function update(Request $request, Assignment $assignment)
    {
        $this->authorizeAssignment($assignment);
        $data = $this->validated($request);
        $course = Course::findOrFail($data['course_id']);
        $this->authorizeCourse($course);
        $this->assertLessonBelongsToCourse($data);

        $oldAttachment = $assignment->attachment;
        if ($request->hasFile('attachment')) {
            $data['attachment'] = $request->file('attachment')->store('assignments/resources', 'local');
        } elseif ($request->filled('resource_url')) {
            $data['attachment'] = $data['resource_url'];
        } else {
            unset($data['attachment']);
        }
        unset($data['resource_url']);

        $assignment->update($data);
        if (isset($data['attachment']) && $data['attachment'] !== $oldAttachment) {
            delete_stored_media('local', $oldAttachment);
        }

        return redirect()->route('teacher.assignments.index')->with('success', 'Assignment updated.');
    }

    public function destroy(Assignment $assignment)
    {
        $this->authorizeAssignment($assignment);
        abort_if($assignment->submissions()->exists(), 409,
            'Assignments with student submissions cannot be deleted. Unpublish the assignment instead.');

        $attachment = $assignment->attachment;
        $assignment->delete();
        delete_stored_media('local', $attachment);

        return redirect()->route('teacher.assignments.index')->with('success', 'Assignment deleted.');
    }

    public function submissions(Assignment $assignment)
    {
        $this->authorizeAssignment($assignment);
        $assignment->load('course');

        $submissions = $assignment->submissions()->with(['student', 'grader'])
            ->orderByDesc('submitted_at')->paginate(20);

        return view('teacher.assignments.submissions', compact('assignment', 'submissions'));
    }

    public function grade(Request $request, Assignment $assignment, AssignmentSubmission $submission)
    {
        $this->authorizeAssignment($assignment);
        abort_unless((int) $submission->assignment_id === (int) $assignment->id, 404);

        $data = $request->validate([
            'status' => 'required|in:graded,returned',
            'score' => 'nullable|numeric|min:0|max:'.$assignment->total_marks,
            'feedback' => 'nullable|string|max:10000',
        ]);

        if ($data['status'] === 'graded' && (! isset($data['score']) || $data['score'] === '')) {
            return back()->withErrors(['score' => 'Enter a score before marking the submission as graded.']);
        }

        $submission->update([
            'status' => $data['status'],
            'score' => $data['status'] === 'graded' ? $data['score'] : null,
            'feedback' => $data['feedback'] ?? null,
            'graded_by' => auth()->id(),
            'graded_at' => now(),
        ]);

        return back()->with('success', $data['status'] === 'returned'
            ? 'Submission returned to the student for revision.'
            : 'Assignment graded successfully.');
    }

    public function downloadSubmission(Assignment $assignment, AssignmentSubmission $submission)
    {
        $this->authorizeAssignment($assignment);
        abort_unless((int) $submission->assignment_id === (int) $assignment->id, 404);
        abort_unless($submission->attachment && Storage::disk('local')->exists($submission->attachment), 404);

        return response()->download(
            Storage::disk('local')->path($submission->attachment),
            basename($submission->attachment),
            ['Cache-Control' => 'private, no-store, max-age=0', 'X-Content-Type-Options' => 'nosniff']
        );
    }

    private function assertLessonBelongsToCourse(array $data): void
    {
        if (! empty($data['lesson_id'])) {
            abort_unless(
                \App\Models\Lesson::whereKey($data['lesson_id'])->where('course_id', $data['course_id'])->exists(),
                422,
                'The selected lesson must belong to the selected course.'
            );
        }
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'course_id' => 'required|exists:courses,id',
            'lesson_id' => 'nullable|exists:lessons,id',
            'title' => 'required|string|max:180',
            'description' => 'nullable|string|max:10000',
            'instructions' => 'nullable|string|max:20000',
            'due_at' => 'nullable|date',
            'total_marks' => 'required|numeric|min:1|max:100000',
            'attachment' => 'nullable|file|max:20480',
            'resource_url' => 'nullable|url:http,https|max:2048',
        ]);

        $data['is_published'] = $request->boolean('is_published');

        return $data;
    }
}
