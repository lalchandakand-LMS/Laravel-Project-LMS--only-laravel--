<?php

namespace App\\Http\\Controllers\\Admin;

use App\\Http\\Controllers\\Controller;
use App\\Models\\MarketingLead;
use Illuminate\\Http\\Request;
use Illuminate\\Validation\\Rule;

class MarketingLeadController extends Controller
{
    public function index(Request $request)
    {
        $query = MarketingLead::query()
            ->when($request->filled('q'), function ($builder) use ($request) {
                $term = trim($request->string('q')->toString());
                $builder->where(function ($search) use ($term) {
                    $search->where('name', 'like', '%'.$term.'%')
                        ->orWhere('email', 'like', '%'.$term.'%')
                        ->orWhere('phone', 'like', '%'.$term.'%')
                        ->orWhere('subject', 'like', '%'.$term.'%');
                });
            })
            ->when($request->filled('status'), fn ($builder) => $builder->where('status', $request->input('status')))
            ->when($request->filled('campaign'), fn ($builder) => $builder->where('utm_campaign', $request->input('campaign')));

        $leads = $query->latest()->paginate(25)->withQueryString();
        $counts = collect(MarketingLead::STATUSES)->mapWithKeys(
            fn ($status) => [$status => MarketingLead::where('status', $status)->count()]
        );
        $campaigns = MarketingLead::query()->whereNotNull('utm_campaign')
            ->where('utm_campaign', '!=', '')
            ->distinct()->orderBy('utm_campaign')->pluck('utm_campaign');

        return view('admin.marketing.leads.index', compact('leads', 'counts', 'campaigns'));
    }

    public function update(Request $request, MarketingLead $lead)
    {
        $validated = $request->validate([
            'status' => ['required', Rule::in(MarketingLead::STATUSES)],
            'admin_notes' => ['nullable', 'string', 'max:5000'],
        ]);

        $validated['admin_notes'] = trim((string) ($validated['admin_notes'] ?? '')) ?: null;
        if ($validated['status'] === 'converted' && $lead->status !== 'converted') {
            $validated['converted_at'] = now();
            $validated['converted_user_id'] = $lead->user_id;
        }

        $lead->update($validated);

        return redirect()->route('admin.marketing.leads.index', $request->only('q', 'status', 'campaign'))
            ->with('success', 'Lead updated.');
    }
}
