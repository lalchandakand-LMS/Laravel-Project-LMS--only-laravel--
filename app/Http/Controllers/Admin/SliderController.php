<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Slider;
use Illuminate\Http\Request;

class SliderController extends Controller
{
    public function index()
    {
        return view('admin.sliders.index', [
            'sliders' => Slider::orderBy('sort_order')->orderBy('id')->paginate(12),
        ]);
    }

    public function create()
    {
        return view('admin.sliders.form', [
            'slider' => new Slider([
                'is_active' => true, 'theme' => 'emerald', 'sort_order' => (int) Slider::max('sort_order') + 1,
                'button_text' => 'Explore courses', 'button_url' => '/courses',
            ]),
            'themes' => Slider::THEMES,
        ]);
    }

    public function store(Request $request)
    {
        Slider::create($this->validated($request));

        return redirect()->route('admin.sliders.index')->with('success', 'Slider added.');
    }

    public function edit(Slider $slider)
    {
        return view('admin.sliders.form', ['slider' => $slider, 'themes' => Slider::THEMES]);
    }

    public function update(Request $request, Slider $slider)
    {
        $data = $this->validated($request, $slider);
        $oldImage = $slider->image;
        $slider->update($data);
        if (isset($data['image']) && $data['image'] !== $oldImage) {
            delete_stored_media('public', $oldImage);
        }

        return redirect()->route('admin.sliders.index')->with('success', 'Slider updated.');
    }

    public function destroy(Slider $slider)
    {
        $image = $slider->image;
        $slider->delete();
        delete_stored_media('public', $image);

        return redirect()->route('admin.sliders.index')->with('success', 'Slider deleted.');
    }

    /** Quick AJAX-free toggle used by the eye button in the list. */
    public function toggle(Slider $slider)
    {
        $slider->update(['is_active' => ! $slider->is_active]);

        return back()->with('success', 'Slider '.($slider->is_active ? 'published' : 'hidden').'.');
    }

    private function validated(Request $request, ?Slider $slider = null): array
    {
        $data = $request->validate([
            'badge' => 'nullable|string|max:80',
            'title' => 'required|string|max:160',
            'subtitle' => 'nullable|string|max:255',
            'image' => 'nullable|image|mimes:jpg,jpeg,png,webp,gif|max:4096',
            'image_url' => 'nullable|url:http,https|max:2048',
            'button_text' => 'nullable|string|max:60',
            'button_url' => 'nullable|string|max:255',
            'theme' => 'required|in:'.implode(',', array_keys(Slider::THEMES)),
            'sort_order' => 'nullable|integer|min:0',
        ]);

        if ($request->hasFile('image')) {
            $data['image'] = $request->file('image')->store('sliders', 'public');
        } elseif ($request->filled('image_url')) {
            $data['image'] = $data['image_url'];
        }
        unset($data['image_url']);

        $data['sort_order'] = (int) ($data['sort_order'] ?? 0);
        $data['is_active'] = $request->boolean('is_active');

        return $data;
    }
}
