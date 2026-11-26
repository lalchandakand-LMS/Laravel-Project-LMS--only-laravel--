<?php

namespace App\\Http\\Controllers\\Admin;

use App\\Http\\Controllers\\Controller;
use App\\Models\\MarketingCampaign;
use Illuminate\\Http\\Request;
use Illuminate\\Validation\\Rule;

class MarketingCampaignController extends Controller
{
    public function index(Request $request)
    {
        $campaigns = MarketingCampaign::query()
            ->withCount('leads')
            ->when($request->filled('q'), fn ($query) => $query->where('name', 'like', '%'.trim($request->string('q')->toString()).'%'))
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->input('status')))
            ->when($request->filled('channel'), fn ($query) => $query->where('channel', $request->input('channel')))
            ->latest()->paginate(20)->withQueryString();

        return view('admin.marketing.campaigns.index', [
            'campaigns' => $campaigns,
            'channels' => MarketingCampaign::CHANNELS,
            'statuses' => MarketingCampaign::STATUSES,
        ]);
    }

    public function create()
    {
        return view('admin.marketing.campaigns.form', [
            'campaign' => new MarketingCampaign(['status' => 'draft', 'channel' => 'facebook', 'source' => 'facebook', 'medium' => 'paid_social']),
            'channels' => MarketingCampaign::CHANNELS,
            'statuses' => MarketingCampaign::STATUSES,
        ]);
    }

    public function store(Request $request)
    {
        $campaign = MarketingCampaign::create([
            ...$this->validated($request),
            'created_by' => $request->user()->id,
        ]);

        return redirect()->route('admin.marketing.campaigns.index')->with('success', 'Campaign created. Tracking URL is ready.');
    }

    public function edit(MarketingCampaign $campaign)
    {
        return view('admin.marketing.campaigns.form', [
            'campaign' => $campaign,
            'channels' => MarketingCampaign::CHANNELS,
            'statuses' => MarketingCampaign::STATUSES,
        ]);
    }

    public function update(Request $request, MarketingCampaign $campaign)
    {
        $campaign->update($this->validated($request));

        return redirect()->route('admin.marketing.campaigns.index')->with('success', 'Campaign updated.');
    }

    public function destroy(MarketingCampaign $campaign)
    {
        $campaign->delete();

        return redirect()->route('admin.marketing.campaigns.index')->with('success', 'Campaign archived. Historical lead attribution is preserved.');
    }

    private function validated(Request $request): array
    {
        $safeDestination = function (string $attribute, mixed $value, \\Closure $fail): void {
            if (! is_string($value) || trim($value) === '') {
                return;
            }

            $url = trim($value);
            if (str_starts_with($url, '/') && ! str_starts_with($url, '//')
                && ! str_contains($url, '\\\\')
                && preg_match('/[\\x00-\\x20]/', $url) !== 1
                && ! str_contains($url, '#')) {
                return;
            }

            $parts = parse_url($url);
            $scheme = strtolower((string) ($parts['scheme'] ?? ''));
            if (! is_array($parts) || ! in_array($scheme, ['http', 'https'], true)
                || blank($parts['host'] ?? null) || isset($parts['user']) || isset($parts['pass'])
                || isset($parts['fragment'])) {
                $fail('Use a same-site path or an absolute HTTP/HTTPS URL without credentials or fragments.');
            }
        };

        return $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'channel' => ['required', Rule::in(MarketingCampaign::CHANNELS)],
            'source' => ['required', 'string', 'max:120'],
            'medium' => ['required', 'string', 'max:120'],
            'term' => ['nullable', 'string', 'max:160'],
            'content' => ['nullable', 'string', 'max:160'],
            'destination_url' => ['required', 'string', 'max:2048', $safeDestination],
            'budget' => ['nullable', 'numeric', 'min:0', 'max:9999999999'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'status' => ['required', Rule::in(MarketingCampaign::STATUSES)],
        ]);
    }
}
