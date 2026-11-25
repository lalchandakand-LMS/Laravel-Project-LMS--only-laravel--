<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\Section;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LessonController extends Controller
{
    public function create(Request $request)
    {
        $course = Course::with('sections')->findOrFail($request->get('course_id'));
        $this->authorizeCourse($course);

        return view('admin.lessons.form', [
            'lesson' => new Lesson(['course_id' => $course->id, 'section_id' => $request->get('section_id'), 'type' => 'video']),
            'course' => $course,
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $this->authorizeCourse(Course::findOrFail($data['course_id']));
        $this->assertSectionBelongsToCourse($data);
        Lesson::create($data);

        return redirect()->route('admin.courses.edit', $data['course_id'])->with('success', 'Lesson added.');
    }

    public function edit(Lesson $lesson)
    {
        $this->authorizeCourse($lesson->course);
        return view('admin.lessons.form', [
            'lesson' => $lesson,
            'course' => $lesson->course->load('sections'),
        ]);
    }

    public function update(Request $request, Lesson $lesson)
    {
        $this->authorizeCourse($lesson->course);
        $data = $this->validated($request);
        $this->authorizeCourse(Course::findOrFail($data['course_id']));
        $this->assertSectionBelongsToCourse($data);
        $lesson->update($data);
        return redirect()->route('admin.courses.edit', $lesson->course_id)->with('success', 'Lesson updated.');
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
        $courseId = $lesson->course_id;
        $lesson->delete();
        return redirect()->route('admin.courses.edit', $courseId)->with('success', 'Lesson deleted.');
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
        ]);

        if ($request->hasFile('attachment')) {
            $data['attachment'] = $request->file('attachment')->store('lessons', 'local');
        }

        $data['is_free_preview'] = $request->boolean('is_free_preview');
        $data['is_published'] = $request->boolean('is_published');
        $data['position'] = $data['position'] ?? 0;

        return $data;
    }
}
