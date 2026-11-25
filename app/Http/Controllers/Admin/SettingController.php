<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\Request;

class SettingController extends Controller
{
    public const KEYS = [
        'site_name', 'site_tagline', 'site_email', 'site_phone', 'site_address',
        'facebook', 'youtube', 'instagram', 'bkash_number', 'nagad_number',
        'currency', 'certificate_signature', 'about_text',
    ];

    public function index()
    {
        return view('admin.settings', ['settings' => Setting::all_()]);
    }

    public function update(Request $request)
    {
        $request->validate([
            'site_name' => ['sometimes', 'required', 'string', 'max:120'],
            'site_tagline' => ['sometimes', 'nullable', 'string', 'max:255'],
            'site_email' => ['sometimes', 'nullable', 'email', 'max:255'],
            'facebook' => ['sometimes', 'nullable', 'url', 'max:2048'],
            'youtube' => ['sometimes', 'nullable', 'url', 'max:2048'],
            'instagram' => ['sometimes', 'nullable', 'url', 'max:2048'],
            'logo' => ['sometimes', 'nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ]);

        foreach (self::KEYS as $key) {
            if ($request->has($key)) {
                Setting::set($key, $request->input($key));
            }
        }

        if ($request->hasFile('logo')) {
            Setting::set('logo', $request->file('logo')->store('brand', 'public'));
        }

        return back()->with('success', 'Settings saved.');
    }
}
