<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Slider;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

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
        $data = $this->validated($request);
        try {
            Slider::create($data);
        } catch (\Throwable $exception) {
            if ($request->hasFile('image') && ! empty($data['image'])) {
                delete_stored_media('public', $data['image']);
            }
            throw $exception;
        }

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
        try {
            $slider->update($data);
        } catch (\Throwable $exception) {
            if ($request->hasFile('image') && ! empty($data['image'])) {
                delete_stored_media('public', $data['image']);
            }
            throw $exception;
        }
        if (isset($data['image']) && $data['image'] !== $oldImage) {
            delete_stored_media('public', $oldImage);
        }

        return redirect()->route('admin.sliders.index')->with('success', 'Slider updated.');
    }

    public function destroy(Slider $slider)
    {
        // Keep the image while the slider is recoverable from the Recycle Bin.
        $slider->delete();

        return redirect()->route('admin.sliders.index')->with('success', 'Slider deleted.');
    }

    /** Quick AJAX-free toggle used by the eye button in the list. */
    public function toggle(Slider $slider)
    {
        $slider->update(['is_active' => ! $slider->is_active]);

        return back()->with('success', 'Slider '.($slider->is_active ? 'published' : 'hidden').'.');
    }

    /**
     * Public CTA links may be same-site root paths, fragments or absolute
     * HTTP(S) URLs only. Reject script/data schemes and protocol-relative URLs.
     */
    private function safeNavigationTarget(): \Closure
    {
        return function (string $attribute, mixed $value, \Closure $fail): void {
            if ($value === null || trim((string) $value) === '') {
                return;
            }

            $url = trim((string) $value);

            if (str_starts_with($url, '#')
                || (str_starts_with($url, '/') && ! str_starts_with($url, '//')
                    && ! str_contains($url, '\\')
                    && preg_match('/[\x00-\x20]/', $url) !== 1)) {
                return;
            }

            $parts = parse_url($url);
            $scheme = strtolower((string) ($parts['scheme'] ?? ''));
            $safeExternal = is_array($parts)
                && in_array($scheme, ['http', 'https'], true)
                && filled($parts['host'] ?? null)
                && ! isset($parts['user'])
                && ! isset($parts['pass']);

            if (! $safeExternal) {
                $fail('Use a same-site path, anchor, or http/https URL.');
            }
        };
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
            'button_url' => ['nullable', 'string', 'max:255', $this->safeNavigationTarget()],
            'theme' => 'required|in:'.implode(',', array_keys(Slider::THEMES)),
            'sort_order' => 'nullable|integer|min:0',
        ]);

        if ($request->hasFile('image')) {
            $data['image'] = $request->file('image')->store('sliders', 'public');
            if (! $data['image']) {
                throw ValidationException::withMessages(['image' => 'The slider image could not be stored. The previous image has been kept.']);
            }
        } elseif ($request->filled('image_url')) {
            $data['image'] = $data['image_url'];
        }
        unset($data['image_url']);

        $data['sort_order'] = (int) ($data['sort_order'] ?? 0);
        $data['is_active'] = $request->boolean('is_active');

        return $data;
    }
}
