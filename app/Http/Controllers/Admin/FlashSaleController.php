<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Course;
use App\Models\Ebook;
use App\Models\FlashSale;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class FlashSaleController extends Controller
{
    public function index()
    {
        $this->ensureAdmin();

        return view('admin.flash-sales.index', [
            'flashSales' => FlashSale::with(['courses:id,title', 'categories:id,name', 'ebooks:id,title'])
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
        [$data, $courseIds, $categoryIds, $ebookIds] = $this->validated($request);

        $sale = FlashSale::create($data);
        $sale->courses()->sync($courseIds);
        $sale->categories()->sync($categoryIds);
        $sale->ebooks()->sync($ebookIds);

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
        [$data, $courseIds, $categoryIds, $ebookIds] = $this->validated($request);

        $flashSale->update($data);
        $flashSale->courses()->sync($courseIds);
        $flashSale->categories()->sync($categoryIds);
        $flashSale->ebooks()->sync($ebookIds);

        return redirect()->route('admin.flash-sales.index')->with('success', 'Flash sale updated.');
    }

    public function toggle(FlashSale $flashSale)
    {
        $this->ensureAdmin();
        $flashSale->update(['is_active' => ! $flashSale->is_active]);

        return back()->with('success', $flashSale->is_active ? 'Flash sale enabled.' : 'Flash sale disabled.');
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
            'ebooks' => Ebook::where('is_published', true)->where('is_free', false)->orderBy('title')->get(['id', 'title', 'price']),
            'selectedCourseIds' => $flashSale->exists ? $flashSale->courses()->pluck('courses.id')->all() : [],
            'selectedCategoryIds' => $flashSale->exists ? $flashSale->categories()->pluck('categories.id')->all() : [],
            'selectedEbookIds' => $flashSale->exists ? $flashSale->ebooks()->pluck('ebooks.id')->all() : [],
        ];
    }

    /** @return array{0:array,1:array<int>,2:array<int>,3:array<int>} */
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
            'ebook_ids' => 'nullable|array',
            'ebook_ids.*' => ['integer', 'distinct', 'exists:ebooks,id'],
        ]);

        $courseIds = array_values(array_unique(array_map('intval', $data['course_ids'] ?? [])));
        $categoryIds = array_values(array_unique(array_map('intval', $data['category_ids'] ?? [])));
        $ebookIds = array_values(array_unique(array_map('intval', $data['ebook_ids'] ?? [])));

        if ($ebookIds !== [] && Ebook::whereIn('id', $ebookIds)->where(function ($query) {
            $query->where('is_free', true)->orWhere('is_published', false);
        })->exists()) {
            throw ValidationException::withMessages([
                'ebook_ids' => 'Flash sales can only target published, paid e-books.',
            ]);
        }

        if ($courseIds === [] && $categoryIds === [] && $ebookIds === []) {
            throw ValidationException::withMessages([
                'course_ids' => 'Select at least one course, category, or paid e-book for this flash sale.',
            ]);
        }

        unset($data['course_ids'], $data['category_ids'], $data['ebook_ids']);
        $data['is_active'] = $request->boolean('is_active');

        return [$data, $courseIds, $categoryIds, $ebookIds];
    }
}
