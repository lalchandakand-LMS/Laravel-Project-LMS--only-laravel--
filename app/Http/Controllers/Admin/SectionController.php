<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Section;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SectionController extends Controller
{
    public function create(Course $course)
    {
        $this->authorizeCourse($course);
        return view('admin.sections.form', [
            'section' => new Section(['course_id' => $course->id]),
            'course' => $course,
        ]);
    }

    public function store(Request $request, Course $course)
    {
        $this->authorizeCourse($course);
        $data = $request->validate([
            'title' => 'required|string|max:180',
            'position' => 'nullable|integer|min:0',
        ]);

        $course->sections()->create($data + ['position' => $data['position'] ?? $course->sections()->count() + 1]);

        return redirect()->route('admin.courses.edit', $course)->with('success', 'Section added.');
    }

    public function edit(Section $section)
    {
        $this->authorizeCourse($section->course);
        return view('admin.sections.form', [
            'section' => $section,
            'course' => $section->course,
        ]);
    }

    public function update(Request $request, Section $section)
    {
        $this->authorizeCourse($section->course);
        $section->update($request->validate([
            'title' => 'required|string|max:180',
            'position' => 'nullable|integer|min:0',
        ]));

        return redirect()->route('admin.courses.edit', $section->course_id)->with('success', 'Section updated.');
    }

    public function reorder(Request $request, Course $course)
    {
        $this->authorizeCourse($course);
        $data = $request->validate([
            'ordered_ids' => 'required|array|min:1',
            'ordered_ids.*' => 'required|integer|distinct',
        ]);

        $currentIds = $course->sections()->pluck('id')->map(fn ($id) => (int) $id)->sort()->values()->all();
        $orderedIds = collect($data['ordered_ids'])->map(fn ($id) => (int) $id)->sort()->values()->all();
        abort_unless($currentIds === $orderedIds, 422, 'Section order does not match this course.');

        DB::transaction(function () use ($data) {
            foreach ($data['ordered_ids'] as $position => $id) {
                Section::whereKey($id)->update(['position' => $position + 1]);
            }
        });

        return response()->json(['ok' => true]);
    }

    private function authorizeCourse(Course $course): void
    {
        $user = auth()->user();
        abort_unless($user && ($user->isAdmin() || (int) $course->instructor_id === (int) $user->id), 403);
    }

    public function destroy(Section $section)
    {
        $this->authorizeCourse($section->course);
        $course = $section->course;

        // Keep lesson records and learning history intact: move lessons to the
        // course-level curriculum before the section enters the recycle bin.
        DB::transaction(function () use ($section) {
            $section->lessons()->update(['section_id' => null]);
            $section->delete();
        });

        return redirect()->route('admin.courses.edit', $course)
            ->with('success', 'Section deleted. Its lessons were kept in the course curriculum.');
    }
}
