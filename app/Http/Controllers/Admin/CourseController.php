<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Course;
use App\Models\Level;\nuse App\Models\MediaAsset;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class CourseController extends Controller
{
    public function index(Request $request)
    {
        $query = Course::with(['category', 'categories', 'instructor'])->withCount(['lessons', 'enrollments']);

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
        $data = $this->validated($request);
        $categoryIds = $data['category_ids'] ?? [];
        unset($data['category_ids']);
        $storedThumbnail = $request->hasFile('thumbnail') ? ($data['thumbnail'] ?? null) : null;
        try {
            $course = DB::transaction(function () use ($data, $categoryIds) {
                $course = Course::create($data);
                $course->categories()->sync($categoryIds);
                return $course;
            });
        } catch (\Throwable $exception) {
            if ($storedThumbnail) {
                delete_stored_media('public', $storedThumbnail);
            }
            throw $exception;
        }
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
        $data = $this->validated($request, $course);
        $categoryIds = $data['category_ids'] ?? [];
        unset($data['category_ids']);
        $oldThumbnail = $course->thumbnail;
        $storedThumbnail = $request->hasFile('thumbnail') ? ($data['thumbnail'] ?? null) : null;
        try {
            DB::transaction(function () use ($course, $data, $categoryIds) {
                $course->update($data);
                $course->categories()->sync($categoryIds);
            });
        } catch (\Throwable $exception) {
            if ($storedThumbnail) {
                delete_stored_media('public', $storedThumbnail);
            }
            throw $exception;
        }
        if (isset($data['thumbnail']) && $data['thumbnail'] !== $oldThumbnail) {
            delete_stored_media('public', $oldThumbnail);
        }
        return redirect()->route('admin.courses.edit', $course)->with('success', 'Course updated.');
    }

    public function destroy(Course $course)
    {
        $this->authorizeOwner($course);
        // Course uses SoftDeletes. Keep all related records and uploaded media intact
        // so a restore returns the course exactly as it was before archiving.
        $course->delete();

        return redirect()->route('admin.courses.index')->with('success', 'Course archived. You can restore it from the Recycle Bin.');
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
            'categories' => Category::where('is_active', true)->with('parent')->orderBy('parent_id')->orderBy('position')->orderBy('name')->get(),
            'selectedCategoryIds' => $course->exists ? $course->categories()->pluck('categories.id')->all() : [],
            'levels' => Level::where('is_active', true)->orderBy('position')->orderBy('name')->get(),
            'instructors' => User::whereIn('role', ['instructor', 'admin'])->orderBy('name')->get(),
        ];
    }

    private function validated(Request $request, ?Course $course = null): array
    {
        $data = $request->validate([
            'title' => 'required|string|max:180',
            'subtitle' => 'nullable|string|max:255',
            'category_id' => ['nullable', Rule::exists('categories', 'id')->where(fn ($query) => $query->where('is_active', true))],
            'category_ids' => 'nullable|array',
            'category_ids.*' => ['integer', 'distinct', Rule::exists('categories', 'id')->where(fn ($query) => $query->where('is_active', true))],
            'level_id' => ['nullable', Rule::exists('levels', 'id')->where(fn ($query) => $query->where('is_active', true))],
            'instructor_id' => ['nullable', Rule::exists('users', 'id')->where(fn ($query) => $query->whereIn('role', ['instructor', 'admin'])->where('is_active', true))],
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
            'access_duration_days' => 'nullable|integer|min:1|max:3650',
            'thumbnail' => 'nullable|image|mimes:jpg,jpeg,png,webp,gif|max:4096',
            'thumbnail_url' => 'nullable|url:http,https|max:2048',
            'promo_video' => 'nullable|string|max:255',
            'meta_title' => 'nullable|string|max:180',
            'meta_description' => 'nullable|string|max:300',
        ], [
            'discount_price.lt' => 'Discount price must be lower than the regular course price.',
        ]);

        $categoryIds = $data['category_ids'] ?? (isset($data['category_id']) ? [$data['category_id']] : []);
        $data['category_ids'] = array_values(array_unique(array_map('intval', $categoryIds)));
        $data['category_id'] = $data['category_ids'][0] ?? null;

        if ($request->hasFile('thumbnail')) {
            $data['thumbnail'] = $request->file('thumbnail')->store('courses', 'public');\n            if ($data['thumbnail']) {\n                MediaAsset::registerUpload('Course thumbnail', 'public', $data['thumbnail'], $request->file('thumbnail'), auth()->id());\n            }
            if (! $data['thumbnail']) {
                throw ValidationException::withMessages(['thumbnail' => 'The course thumbnail could not be stored. The previous thumbnail has been kept.']);
            }
        } elseif ($request->filled('thumbnail_url')) {
            $data['thumbnail'] = $data['thumbnail_url'];
        }
        unset($data['thumbnail_url']);

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
