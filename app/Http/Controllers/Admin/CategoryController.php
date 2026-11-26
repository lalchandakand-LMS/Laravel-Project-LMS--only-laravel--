<?php

namespace App\\Http\\Controllers\\Admin;

use App\\Http\\Controllers\\Controller;
use App\\Models\\Category;
use Illuminate\\Http\\Request;
use Illuminate\\Support\\Facades\\DB;
use Illuminate\\Support\\Str;
use Illuminate\\Validation\\Rule;

class CategoryController extends Controller
{
    public function index(Request $request)
    {
        $query = Category::with(['parent', 'children'])->withCount(['courses', 'courseLinks']);
        if ($search = $request->string('q')->toString()) {
            $query->where('name', 'like', "%{$search}%");
        }

        $categories = $query->orderBy('position')->orderBy('name')->paginate(30)->withQueryString();

        return view('admin.categories.index', compact('categories'));
    }

    public function create()
    {
        return view('admin.categories.form', [
            'category' => new Category(['is_active' => true]),
            'parents' => Category::orderBy('position')->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        Category::create($data);

        return redirect()->route('admin.categories.index')->with('success', 'Category created.');
    }

    public function edit(Category $category)
    {
        return view('admin.categories.form', [
            'category' => $category,
            'parents' => Category::where('id', '!=', $category->id)->orderBy('position')->orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, Category $category)
    {
        $data = $this->validated($request, $category);
        $category->update($data);

        return redirect()->route('admin.categories.index')->with('success', 'Category updated.');
    }

    public function destroy(Category $category)
    {
        DB::transaction(function () use ($category) {
            $fallbackParentId = $category->parent_id;
            $category->children()->update(['parent_id' => $fallbackParentId]);
            $category->courses()->update(['category_id' => $fallbackParentId]);
            $category->delete();
        });

        return redirect()->route('admin.categories.index')->with('success', 'Category moved to Recycle Bin. Linked courses and subcategories were safely reassigned.');
    }

    private function validated(Request $request, ?Category $category = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'slug' => ['nullable', 'string', 'max:160', 'alpha_dash', Rule::unique('categories', 'slug')->ignore($category?->id)],
            'parent_id' => ['nullable', 'integer', 'exists:categories,id', function ($attribute, $value, $fail) use ($category) {
                if (! $value || ! $category) return;
                if ((int) $value === (int) $category->id) {
                    $fail('A category cannot be its own parent.');
                    return;
                }
                $descendantIds = $category->descendants()->pluck('id')->all();
                if (in_array((int) $value, array_map('intval', $descendantIds), true)) {
                    $fail('A category cannot be moved under one of its own subcategories.');
                }
            }],
            'icon' => ['nullable', 'string', 'max:60'],
            'description' => ['nullable', 'string', 'max:2000'],
            'image' => ['nullable', 'image', 'max:4096'],
            'meta_title' => ['nullable', 'string', 'max:180'],
            'meta_description' => ['nullable', 'string', 'max:300'],
            'position' => ['nullable', 'integer', 'min:0', 'max:100000'],
        ]);

        if ($request->hasFile('image')) {
            $data['image'] = $request->file('image')->store('categories', 'public');
        }
        $data['slug'] = filled($data['slug'] ?? null) ? Str::slug($data['slug']) : null;
        $data['is_active'] = $request->boolean('is_active');
        $data['position'] = $data['position'] ?? 0;

        return $data;
    }
}
