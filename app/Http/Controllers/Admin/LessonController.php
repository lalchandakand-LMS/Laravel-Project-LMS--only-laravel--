<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\Section;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LessonController extends Controller
{
    public function create(Request $request)
    {
        $course = Course::with('sections')->findOrFail($request->get('course_id'));
        $this->authorizeCourse($course);

        return view('admin.lessons.form', [
            'lesson' => new Lesson(['course_id' => $course->id, 'section_id' => $request->get('section_id'), 'type' => 'video']),
            'course' => $course,
            'lessons' => $course->lessons()->orderBy('position')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $course = Course::findOrFail($data['course_id']);
        $this->authorizeCourse($course);
        $this->assertSectionBelongsToCourse($data);
        $this->assertPrerequisiteBelongsToCourse($data);
        $storedAttachment = $this->applyAttachment($request, $data);
        try {
            Lesson::create($data);
        } catch (\Throwable $exception) {
            if ($storedAttachment) {
                delete_stored_media('local', $storedAttachment);
            }
            throw $exception;
        }

        return redirect()->route('admin.courses.edit', $course)->with('success', 'Lesson added.');
    }

    public function edit(Lesson $lesson)
    {
        $this->authorizeCourse($lesson->course);
        return view('admin.lessons.form', [
            'lesson' => $lesson,
            'course' => $lesson->course->load('sections'),
            'lessons' => $lesson->course->lessons()->where('id', '!=', $lesson->id)->orderBy('position')->get(),
        ]);
    }

    public function update(Request $request, Lesson $lesson)
    {
        $this->authorizeCourse($lesson->course);
        $data = $this->validated($request);
        $course = Course::findOrFail($data['course_id']);
        $this->authorizeCourse($course);
        $this->assertSectionBelongsToCourse($data);
        $this->assertPrerequisiteBelongsToCourse($data, $lesson);
        $this->assertLessonCourseChangeAllowed($lesson, $data);
        $oldAttachment = $lesson->attachment;
        $storedAttachment = $this->applyAttachment($request, $data);
        try {
            $lesson->update($data);
        } catch (\Throwable $exception) {
            if ($storedAttachment) {
                delete_stored_media('local', $storedAttachment);
            }
            throw $exception;
        }
        if (isset($data['attachment']) && $data['attachment'] !== $oldAttachment) {
            delete_stored_media('local', $oldAttachment);
        }
        return redirect()->route('admin.courses.edit', $course)->with('success', 'Lesson updated.');
    }

    public function reorder(Request $request, Section $section)
    {
        $this->authorizeCourse($section->course);
        $data = $request->validate([
            'ordered_ids' => 'required|array|min:1',
            'ordered_ids.*' => 'required|integer|distinct',
        ]);

        $currentIds = $section->lessons()->pluck('id')->map(fn ($id) => (int) $id)->sort()->values()->all();
        $orderedIds = collect($data['ordered_ids'])->map(fn ($id) => (int) $id)->sort()->values()->all();
        abort_unless($currentIds === $orderedIds, 422, 'Lesson order does not match this section.');

        DB::transaction(function () use ($data) {
            foreach ($data['ordered_ids'] as $position => $id) {
                Lesson::whereKey($id)->update(['position' => $position + 1]);
            }
        });

        return response()->json(['ok' => true]);
    }

    public function destroy(Lesson $lesson)
    {
        $this->authorizeCourse($lesson->course);
        abort_if(
            $lesson->dependents()->exists(),
            409,
            'This lesson is a prerequisite for other lessons. Reassign those prerequisites before archiving it.'
        );

        $course = $lesson->course;
        // Keep the private attachment while the lesson remains recoverable.
        $lesson->delete();

        return redirect()->route('admin.courses.edit', $course)->with('success', 'Lesson deleted.');
    }


    private function applyAttachment(Request $request, array &$data): ?string
    {
        $storedAttachment = null;

        if ($request->hasFile('attachment')) {
            $storedAttachment = $request->file('attachment')->store('lessons', 'local');
            if (! $storedAttachment) {
                throw ValidationException::withMessages(['attachment' => 'The lesson attachment could not be stored.']);
            }
            $data['attachment'] = $storedAttachment;
        } elseif ($request->filled('attachment_url')) {
            $data['attachment'] = $data['attachment_url'];
        }

        unset($data['attachment_url']);

        return $storedAttachment;
    }

    private function authorizeCourse(Course $course): void
    {
        $user = auth()->user();
        abort_unless($user && ($user->isAdmin() || (int) $course->instructor_id === (int) $user->id), 403);
    }

    private function assertSectionBelongsToCourse(array $data): void
    {
        if (! empty($data['section_id'])) {
            abort_unless(Section::whereKey($data['section_id'])->where('course_id', $data['course_id'])->exists(), 422);
        }
    }


    /**
     * A lesson with learning history or linked curriculum records cannot be
     * moved to another course; that would orphan prerequisites and shift
     * existing progress, exams, or assignments into a different course.
     */
    private function assertLessonCourseChangeAllowed(Lesson $lesson, array $data): void
    {
        if ((int) $data['course_id'] === (int) $lesson->course_id) {
            return;
        }

        $hasLinkedRecords = DB::table('lessons')->where('prerequisite_lesson_id', $lesson->id)->exists()
            || DB::table('lesson_progress')->where('lesson_id', $lesson->id)->exists()
            || DB::table('exams')->where('lesson_id', $lesson->id)->exists()
            || DB::table('assignments')->where('lesson_id', $lesson->id)->exists();

        if ($hasLinkedRecords) {
            throw ValidationException::withMessages([
                'course_id' => 'This lesson has learning history or linked content. Remove or reassign those links before moving it to another course.',
            ]);
        }
    }

    private function assertPrerequisiteBelongsToCourse(array $data, ?Lesson $lesson = null): void
    {
        if (empty($data['prerequisite_lesson_id'])) {
            return;
        }

        $prerequisite = Lesson::whereKey($data['prerequisite_lesson_id'])
            ->where('course_id', $data['course_id'])->first();
        abort_unless($prerequisite && (! $lesson || $prerequisite->id !== $lesson->id), 422,
            'A prerequisite must be a different lesson in the same course.');

        $cursor = $prerequisite;
        $visited = [];
        while ($cursor && $cursor->prerequisite_lesson_id) {
            if ($lesson && (int) $cursor->prerequisite_lesson_id === (int) $lesson->id) {
                abort(422, 'This prerequisite would create a circular lesson dependency.');
            }
            if (in_array($cursor->id, $visited, true)) {
                abort(422, 'The prerequisite chain already contains a cycle.');
            }
            $visited[] = $cursor->id;
            $cursor = Lesson::find($cursor->prerequisite_lesson_id);
        }
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'course_id' => 'required|exists:courses,id',
            'section_id' => 'nullable|exists:sections,id',
            'title' => 'required|string|max:180',
            'type' => 'required|in:video,text,pdf,live,exam',
            'video_url' => 'nullable|string|max:255',
            'content' => 'nullable|string',
            'duration_minutes' => 'nullable|integer|min:0',
            'position' => 'nullable|integer|min:0',
            'attachment' => 'nullable|file|max:20480',
            'attachment_url' => 'nullable|url:http,https|max:2048',
            'available_at' => 'nullable|date',
            'prerequisite_lesson_id' => 'nullable|integer|exists:lessons,id',
        ]);

        $data['is_free_preview'] = $request->boolean('is_free_preview');
        $data['is_published'] = $request->boolean('is_published');
        $data['position'] = $data['position'] ?? 0;

        return $data;
    }
}
