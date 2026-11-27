<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\DispatchNewsletterCampaign;
use App\Models\NewsletterCampaign;
use App\Models\NewsletterSubscriber;
use Illuminate\Http\Request;

class NewsletterCampaignController extends Controller
{
    public function index()
    {
        return view('admin.marketing.newsletter.campaigns', [
            'campaigns' => NewsletterCampaign::query()
                ->with('creator')
                ->withCount('deliveries')
                ->latest()
                ->paginate(20),
            'subscribedCount' => NewsletterSubscriber::where('status', 'subscribed')->count(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'subject' => ['required', 'string', 'max:180', 'regex:/^[^\\r\\n]+$/u'],
            'title' => ['required', 'string', 'max:180'],
            'body' => ['required', 'string', 'max:12000'],
            'confirm_send' => ['accepted'],
        ]);

        if (app()->environment('production') && config('queue.default') === 'sync') {
            return back()->withErrors([
                'queue' => 'Bulk email is disabled while the production queue connection is synchronous. Configure the database queue and a queue worker before sending.',
            ])->withInput();
        }

        $campaign = NewsletterCampaign::create([
            'created_by' => $request->user()->id,
            'subject' => trim($validated['subject']),
            'title' => trim($validated['title']),
            'body' => trim($validated['body']),
            'status' => 'queued',
            'queued_at' => now(),
        ]);

        DispatchNewsletterCampaign::dispatch($campaign->id);

        return redirect()->route('admin.marketing.newsletter.campaigns')
            ->with('success', 'Newsletter campaign queued. It will be sent only to confirmed subscribers.');
    }
}
