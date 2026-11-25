<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MenuItem;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class MenuSettingController extends Controller
{
    public function index(): View
    {
        return view('admin.menu-settings.index', [
            'menus' => MenuItem::with('children')->orderBy('location')->orderBy('sort_order')->get(),
            'parents' => MenuItem::whereNull('parent_id')->orderBy('location')->orderBy('sort_order')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        MenuItem::create($this->validated($request));
        return back()->with('success', 'Menu item created.');
    }

    public function update(Request $request, MenuItem $menuSetting): RedirectResponse
    {
        $menuSetting->update($this->validated($request, $menuSetting));
        return back()->with('success', 'Menu item updated.');
    }

    public function destroy(MenuItem $menuSetting): RedirectResponse
    {
        // Promote children to top-level so deleting a parent never hides menu links.
        $menuSetting->children()->update(['parent_id' => null]);
        $menuSetting->delete();

        return back()->with('success', 'Menu item deleted.');
    }

    public function reorder(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.id' => ['required', 'integer', 'distinct', 'exists:menu_items,id'],
            'items.*.sort_order' => ['required', 'integer', 'min:0', 'max:10000'],
        ]);

        foreach ($data['items'] as $item) {
            MenuItem::whereKey($item['id'])->update(['sort_order' => $item['sort_order']]);
        }

        return back()->with('success', 'Menu order saved.');
    }

    private function validated(Request $request, ?MenuItem $current = null): array
    {
        $data = $request->validate([
            'label' => ['required', 'string', 'max:80'],
            'url' => ['required', 'string', 'max:2048'],
            'location' => ['required', Rule::in(['header', 'footer'])],
            'parent_id' => ['nullable', 'integer', 'exists:menu_items,id', Rule::notIn([$current?->id])],
            'sort_order' => ['required', 'integer', 'min:0', 'max:10000'],
            'icon' => ['nullable', 'string', 'max:60'],
            'open_new_tab' => ['nullable', 'boolean'],
            'is_enabled' => ['nullable', 'boolean'],
            'show_desktop' => ['nullable', 'boolean'],
            'show_mobile' => ['nullable', 'boolean'],
        ]);

        if (str_starts_with($data['url'], '//') ||
            (! str_starts_with($data['url'], '/') && ! filter_var($data['url'], FILTER_VALIDATE_URL))) {
            validator([], [])->errors(); // Keep the validation failure in Laravel's normal error flow below.
            abort(422, 'Use a site-relative path (starting with /) or a complete http/https URL.');
        }

        if (filter_var($data['url'], FILTER_VALIDATE_URL) &&
            ! in_array(parse_url($data['url'], PHP_URL_SCHEME), ['http', 'https'], true)) {
            abort(422, 'Only HTTP and HTTPS menu links are allowed.');
        }

        if ($current && $data['parent_id'] && (int) $data['parent_id'] === $current->id) {
            abort(422, 'A menu item cannot be its own parent.');
        }

        $data['open_new_tab'] = $request->boolean('open_new_tab');
        $data['is_enabled'] = $request->boolean('is_enabled');
        $data['show_desktop'] = $request->boolean('show_desktop');
        $data['show_mobile'] = $request->boolean('show_mobile');

        return $data;
    }
}
