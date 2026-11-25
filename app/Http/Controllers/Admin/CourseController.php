<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Course;
use App\Models\Level;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CourseController extends Controller
{
    public function index(Request $request)
    {
        $query = Course::with(['category', 'instructor'])->withCount(['lessons', 'enrollments']);

        if (! auth()->user()->isAdmin()) {
            $query->where('instructor_id', auth()->id());
        }

        if ($q = $request->get('q')) {
            $query->where('title', 'like', "%{$q}%");
        }

        return view('admin.courses.index', [
            'courses' => $query->latest()->paginate(12)->withQueryString(),
        ]);
    }

    public function create()
    {
        return view('admin.courses.form', $this->formData(new Course([
            'is_published' => true, 'language' => 'Bangla', 'level' => 'beginner',
        ])));
    }

    public function store(Request $request)
    {
        $course = Course::create($this->validated($request));
        return redirect()->route('admin.courses.edit', $course)
            ->with('success', 'Course created. Now build the curriculum.');
    }

    public function show(Course $course)
    {
        $this->authorizeOwner($course);
        return redirect()->route('admin.courses.edit', $course);
    }

    public function edit(Course $course)
    {
        $this->authorizeOwner($course);
        return view('admin.courses.form', $this->formData($course->load('sections.lessons', 'exams')));
    }

    public function update(Request $request, Course $course)
    {
        $this->authorizeOwner($course);
        $course->update($this->validated($request, $course));
        return redirect()->route('admin.courses.edit', $course)->with('success', 'Course updated.');
    }

    public function destroy(Course $course)
    {
        $this->authorizeOwner($course);
        $course->delete();
        return redirect()->route('admin.courses.index')->with('success', 'Course deleted.');
    }

    private function authorizeOwner(Course $course): void
    {
        $user = auth()->user();
        abort_unless($user && ($user->isAdmin() || (int) $course->instructor_id === (int) $user->id), 403);
    }

    private function formData(Course $course): array
    {
        return [
            'course' => $course,
            'categories' => Category::orderBy('name')->get(),
            'levels' => Level::where('is_active', true)->orderBy('position')->orderBy('name')->get(),
            'instructors' => User::whereIn('role', ['instructor', 'admin'])->orderBy('name')->get(),
        ];
    }

    private function validated(Request $request, ?Course $course = null): array
    {
        $data = $request->validate([
            'title' => 'required|string|max:180',
            'subtitle' => 'nullable|string|max:255',
            'category_id' => 'nullable|exists:categories,id',
            'level_id' => 'nullable|exists:levels,id',
            'instructor_id' => 'nullable|exists:users,id',
            'description' => 'nullable|string',
            'requirements' => 'nullable|string',
            'outcomes' => 'nullable|string',
            'price' => 'required|numeric|min:0',
            'discount_price' => 'nullable|numeric|min:0|lt:price',
            'level' => 'nullable|string|max:50',
            'language' => 'required|string|max:30',
            'duration_minutes' => 'nullable|integer|min:0',
            'starts_at' => 'nullable|date',
            'seat_limit' => 'nullable|integer|min:1',
            'thumbnail' => 'nullable|image|max:4096',
            'promo_video' => 'nullable|string|max:255',
            'meta_title' => 'nullable|string|max:180',
            'meta_description' => 'nullable|string|max:300',
        ], [
            'discount_price.lt' => 'Discount price must be lower than the regular course price.',
        ]);

        if ($request->hasFile('thumbnail')) {
            $data['thumbnail'] = $request->file('thumbnail')->store('courses', 'public');
        }

        $data['outcomes'] = collect(preg_split('/\r
|\r|
/', (string) $request->input('outcomes')))
            ->map(fn ($l) => trim($l))->filter()->values()->all();

        if (! empty($data['level_id'])) {
            $managedLevel = Level::findOrFail($data['level_id']);
            $label = strtolower($managedLevel->name);
            $data['level'] = str_contains($label, 'advanced') ? 'advanced' : (str_contains($label, 'intermediate') ? 'intermediate' : 'beginner');
        } else {
            $data['level'] = $data['level'] ?? 'beginner';
        }

        $data['is_published'] = $request->boolean('is_published');
        $data['is_featured'] = $request->boolean('is_featured');
        $data['has_certificate'] = $request->boolean('has_certificate');

        if (! auth()->user()->isAdmin()) {
            $data['instructor_id'] = auth()->id();
        }

        return $data;
    }
}
