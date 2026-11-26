<?php

namespace App\\Http\\Controllers\\Admin;

use App\\Http\\Controllers\\Controller;
use App\\Models\\MarketingPopup;
use Illuminate\\Http\\Request;
use Illuminate\\Validation\\Rule;

class MarketingPopupController extends Controller
{
    public function index()
    {
        $popups = MarketingPopup::query()->latest()->paginate(20);

        return view('admin.marketing.popups.index', compact('popups'));
    }

    public function create()
    {
        return view('admin.marketing.popups.form', [
            'popup' => new MarketingPopup([
                'status' => 'draft',
                'target_audience' => 'all',
                'display_on' => 'all_public_pages',
                'frequency' => 'once_per_session',
            ]),
        ]);
    }

    public function store(Request $request)
    {
        MarketingPopup::create($this->validated($request) + ['created_by' => $request->user()->id]);

        return redirect()->route('admin.marketing.popups.index')->with('success', 'Popup created.');
    }

    public function edit(MarketingPopup $popup)
    {
        return view('admin.marketing.popups.form', compact('popup'));
    }

    public function update(Request $request, MarketingPopup $popup)
    {
        $popup->update($this->validated($request));

        return redirect()->route('admin.marketing.popups.index')->with('success', 'Popup updated.');
    }

    public function destroy(MarketingPopup $popup)
    {
        $popup->delete();

        return redirect()->route('admin.marketing.popups.index')->with('success', 'Popup archived.');
    }

    private function validated(Request $request): array
    {
        $safeUrl = function (string $attribute, mixed $value, \\Closure $fail): void {
            if (blank($value)) return;
            $url = trim((string) $value);
            if (str_starts_with($url, '/') && ! str_starts_with($url, '//')
                && ! str_contains($url, '\\\\') && ! str_contains($url, '#')
                && preg_match('/[\\x00-\\x20]/', $url) !== 1) return;
            $parts = parse_url($url);
            if (! is_array($parts) || strtolower((string) ($parts['scheme'] ?? '')) !== 'https'
                || blank($parts['host'] ?? null) || isset($parts['user']) || isset($parts['pass']) || isset($parts['fragment'])) {
                $fail('Use a same-site path or an absolute HTTPS URL without credentials or fragments.');
            }
        };

        return $request->validate([
            'title' => ['required', 'string', 'max:160'],
            'body' => ['nullable', 'string', 'max:3000'],
            'image_url' => ['nullable', 'string', 'max:2048', $safeUrl],
            'cta_label' => ['nullable', 'string', 'max:60'],
            'cta_url' => ['nullable', 'string', 'max:2048', 'required_with:cta_label', $safeUrl],
            'target_audience' => ['required', Rule::in(MarketingPopup::AUDIENCES)],
            'display_on' => ['required', Rule::in(MarketingPopup::DISPLAY_OPTIONS)],
            'frequency' => ['required', Rule::in(MarketingPopup::FREQUENCIES)],
            'priority' => ['required', 'integer', 'min:0', 'max:10000'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'status' => ['required', Rule::in(MarketingPopup::STATUSES)],
        ]);
    }
}
