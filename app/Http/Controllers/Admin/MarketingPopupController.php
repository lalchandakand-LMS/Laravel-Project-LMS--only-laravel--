<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class MarketingPopupController extends Controller
{
    public function edit()
    {
        return view('admin.marketing.popup', ['settings' => Setting::all_()]);
    }

    public function update(Request $request)
    {
        $validated = $request->validate([
            'enabled' => ['nullable', 'boolean'],
            'title' => ['required', 'string', 'max:120'],
            'body' => ['required', 'string', 'max:500'],
            'button_text' => ['required', 'string', 'max:50'],
            'delay' => ['required', 'integer', 'min:0', 'max:60'],
            'frequency' => ['required', Rule::in(['session', 'day', 'week'])],
            'display' => ['required', Rule::in(['home', 'public'])],
            'destination_url' => ['nullable', 'string', 'max:2048'],
        ]);

        $destination = trim((string) ($validated['destination_url'] ?? ''));
        if ($destination !== '' && (
            ! str_starts_with($destination, '/') || str_starts_with($destination, '//')
            || str_contains($destination, '\\') || preg_match('/[\x00-\x20]/', $destination)
        )) {
            return back()->withErrors(['destination_url' => 'Use a same-site path beginning with / (external and protocol-relative URLs are not allowed).'])->withInput();
        }

        Setting::set('marketing_popup_enabled', $request->boolean('enabled') ? '1' : '0');
        Setting::set('marketing_popup_title', trim($validated['title']));
        Setting::set('marketing_popup_body', trim($validated['body']));
        Setting::set('marketing_popup_button_text', trim($validated['button_text']));
        Setting::set('marketing_popup_delay', (string) $validated['delay']);
        Setting::set('marketing_popup_frequency', $validated['frequency']);
        Setting::set('marketing_popup_display', $validated['display']);
        Setting::set('marketing_popup_destination_url', $destination);

        return redirect()->route('admin.marketing.popup.edit')->with('success', 'Marketing popup settings saved.');
    }
}
