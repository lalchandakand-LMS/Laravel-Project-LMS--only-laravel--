<?php

namespace App\Http\Controllers;

use App\Models\MarketingLead;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class MarketingPopupController extends Controller
{
    public function capture(Request $request)
    {
        abort_unless(Setting::get('marketing_popup_enabled', '0') === '1', 404);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email:rfc', 'max:255'],
            'phone' => ['nullable', 'string', 'max:40'],
            'consent' => ['accepted'],
            'website' => ['nullable', 'string', 'max:200'],
        ]);

        if (filled($validated['website'] ?? null)) {
            return back()->with('success', 'Thank you. Our team will contact you soon.');
        }

        $attribution = $request->session()->get('marketing_attribution', []);
        $attribution = is_array($attribution) ? $attribution : [];

        MarketingLead::create([
            'user_id' => $request->user()?->id,
            'name' => trim($validated['name']),
            'email' => mb_strtolower(trim($validated['email'])),
            'phone' => filled($validated['phone'] ?? null) ? trim($validated['phone']) : null,
            'subject' => 'Website popup enquiry',
            'message' => 'Visitor requested information through the website marketing popup.',
            'source' => 'marketing_popup',
            'utm_source' => $attribution['utm_source'] ?? null,
            'utm_medium' => $attribution['utm_medium'] ?? null,
            'utm_campaign' => $attribution['utm_campaign'] ?? null,
            'utm_term' => $attribution['utm_term'] ?? null,
            'utm_content' => $attribution['utm_content'] ?? null,
            'landing_page' => $attribution['landing_page'] ?? '/'.ltrim($request->path(), '/'),
            'referrer_host' => $attribution['referrer_host'] ?? null,
            'status' => 'new',
        ]);

        $destination = (string) Setting::get('marketing_popup_destination_url', '');

        return ($destination !== '' ? redirect($destination) : back())
            ->with('success', 'Thank you! Our team will contact you soon.')
            ->with('aec_lead_event', ['event_id' => (string) Str::uuid(), 'method' => 'marketing_popup']);
    }
}
