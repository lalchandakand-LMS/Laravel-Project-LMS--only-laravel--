<?php

namespace App\\Http\\Controllers\\Admin;

use App\\Http\\Controllers\\Controller;
use App\\Models\\Category;
use App\\Models\\Course;
use App\\Models\\FlashSale;
use Illuminate\\Http\\Request;
use Illuminate\\Validation\\ValidationException;

class FlashSaleController extends Controller
{
    public function index()
    {
        $this->ensureAdmin();

        return view('admin.flash-sales.index', [
            'flashSales' => FlashSale::with(['courses:id,title', 'categories:id,name'])
                ->latest()->paginate(20),
        ]);
    }

    public function create()
    {
        $this->ensureAdmin();

        return view('admin.flash-sales.form', $this->formData(new FlashSale([
            'type' => 'percent', 'is_active' => true,
            'starts_at' => now(), 'ends_at' => now()->addDay(),
        ])));
    }

    public function store(Request $request)
    {
        $this->ensureAdmin();
        [$data, $courseIds, $categoryIds] = $this->validated($request);

        $sale = FlashSale::create($data);
        $sale->courses()->sync($courseIds);
        $sale->categories()->sync($categoryIds);

        return redirect()->route('admin.flash-sales.index')->with('success', 'Flash sale created.');
    }

    public function edit(FlashSale $flashSale)
    {
        $this->ensureAdmin();

        return view('admin.flash-sales.form', $this->formData($flashSale));
    }

    public function update(Request $request, FlashSale $flashSale)
    {
        $this->ensureAdmin();
        [$data, $courseIds, $categoryIds] = $this->validated($request);

        $flashSale->update($data);
        $flashSale->courses()->sync($courseIds);
        $flashSale->categories()->sync($categoryIds);

        return redirect()->route('admin.flash-sales.index')->with('success', 'Flash sale updated.');
    }

    public function destroy(FlashSale $flashSale)
    {
        $this->ensureAdmin();
        $flashSale->delete();

        return back()->with('success', 'Flash sale archived. Restore it from Recycle Bin if needed.');
    }

    private function ensureAdmin(): void
    {
        abort_unless(auth()->user()?->isAdmin(), 403);
    }

    private function formData(FlashSale $flashSale): array
    {
        return [
            'flashSale' => $flashSale,
            'courses' => Course::orderBy('title')->get(['id', 'title', 'category_id']),
            'categories' => Category::where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'selectedCourseIds' => $flashSale->exists ? $flashSale->courses()->pluck('courses.id')->all() : [],
            'selectedCategoryIds' => $flashSale->exists ? $flashSale->categories()->pluck('categories.id')->all() : [],
        ];
    }

    /** @return array{0:array,1:array<int>,2:array<int>} */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => 'required|string|max:160',
            'type' => 'required|in:percent,fixed',
            'value' => ['required', 'numeric', 'gt:0', $request->input('type') === 'percent' ? 'max:100' : 'max:10000000'],
            'description' => 'nullable|string|max:2000',
            'starts_at' => 'required|date',
            'ends_at' => 'required|date|after:starts_at',
            'course_ids' => 'nullable|array',
            'course_ids.*' => 'integer|distinct|exists:courses,id',
            'category_ids' => 'nullable|array',
            'category_ids.*' => 'integer|distinct|exists:categories,id',
        ]);

        $courseIds = array_values(array_unique(array_map('intval', $data['course_ids'] ?? [])));
        $categoryIds = array_values(array_unique(array_map('intval', $data['category_ids'] ?? [])));
        if ($courseIds === [] && $categoryIds === []) {
            throw ValidationException::withMessages([
                'course_ids' => 'Select at least one course or category for this flash sale.',
            ]);
        }

        unset($data['course_ids'], $data['category_ids']);
        $data['is_active'] = $request->boolean('is_active');

        return [$data, $courseIds, $categoryIds];
    }
}
