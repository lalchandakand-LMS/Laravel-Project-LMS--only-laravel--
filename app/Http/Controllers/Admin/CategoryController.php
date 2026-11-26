<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class CategoryController extends Controller
{
    public function index()
    {
        return view('admin.categories.index', [
            'categories' => Category::withCount('courses')->orderBy('position')->paginate(20),
        ]);
    }

    public function create()
    {
        return view('admin.categories.form', [
            'category' => new Category(['is_active' => true]),
            'parents' => Category::whereNull('parent_id')->get(),
        ]);
    }

    public function store(Request $request)
    {
        Category::create($this->validated($request));
        return redirect()->route('admin.categories.index')->with('success', 'Category created.');
    }

    public function edit(Category $category)
    {
        return view('admin.categories.form', [
            'category' => $category,
            'parents' => Category::whereNull('parent_id')->where('id', '!=', $category->id)->get(),
        ]);
    }

    public function update(Request $request, Category $category)
    {
        $category->update($this->validated($request, $category));
        return redirect()->route('admin.categories.index')->with('success', 'Category updated.');
    }

    public function destroy(Category $category)
    {
        $category->delete();
        return back()->with('success', 'Category deleted.');
    }

    private function validated(Request $request, ?Category $category = null): array
    {
        $data = $request->validate([
            'name' => 'required|string|max:120',
            'parent_id' => 'nullable|exists:categories,id',
            'icon' => 'nullable|string|max:60',
            'image' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:4096',
            'image_url' => ['nullable', 'url', 'max:2048', 'regex:/^https:\/\//i'],
            'description' => 'nullable|string|max:500',
            'position' => 'nullable|integer|min:0',
        ]);

        if ($request->hasFile('image')) {
            $newImage = $request->file('image')->store('categories', 'public');
            if ($category?->image && ! str_starts_with($category->image, 'https://')) {
                Storage::disk('public')->delete($category->image);
            }
            $data['image'] = $newImage;
        } elseif (! empty($data['image_url'])) {
            if ($category?->image && ! str_starts_with($category->image, 'https://')) {
                Storage::disk('public')->delete($category->image);
            }
            $data['image'] = $data['image_url'];
        }
        unset($data['image_url']);

        $data['is_active'] = $request->boolean('is_active');
        $data['position'] = $data['position'] ?? 0;

        return $data;
    }
}
