<?php

namespace App\\Http\\Controllers\\Admin;

use App\\Http\\Controllers\\Controller;
use App\\Models\\Level;
use Illuminate\\Http\\Request;
use Illuminate\\Validation\\Rule;

class LevelController extends Controller
{
    public function index(Request $request)
    {
        $levels = Level::withCount('courses')
            ->when($request->filled('q'), fn ($query) => $query->where('name', 'like', '%'.$request->string('q').'%'))
            ->orderBy('position')->orderBy('name')->paginate(20)->withQueryString();

        return view('admin.levels.index', compact('levels'));
    }

    public function create() { return view('admin.levels.form', ['level' => new Level(['is_active' => true])]); }

    public function store(Request $request)
    {
        Level::create($this->validated($request));
        return redirect()->route('admin.levels.index')->with('success', 'Level created.');
    }

    public function edit(Level $level) { return view('admin.levels.form', compact('level')); }

    public function update(Request $request, Level $level)
    {
        $level->update($this->validated($request, $level));
        return redirect()->route('admin.levels.index')->with('success', 'Level updated.');
    }

    public function destroy(Level $level)
    {
        $level->delete();
        return redirect()->route('admin.levels.index')->with('success', 'Level moved to the Recycle Bin. Linked courses and batches are preserved.');
    }

    private function validated(Request $request, ?Level $level = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique('levels', 'name')->ignore($level?->id)],
            'description' => 'nullable|string|max:2000',
            'position' => 'nullable|integer|min:0|max:65535',
            'is_active' => 'nullable|boolean',
        ]) + ['is_active' => $request->boolean('is_active')];
    }
}
