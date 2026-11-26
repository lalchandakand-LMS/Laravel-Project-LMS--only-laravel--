<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MarketingLead;
use App\Services\Sms\MiMSmsGateway;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

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

        $leads->getCollection()->each(function (MarketingLead $lead) {
            $number = app(MiMSmsGateway::class)->normalizeBangladeshNumber($lead->phone);
            $message = "Assalamu Alaikum {$lead->name}, thank you for contacting Alom's English Center. How can our team help you?";
            $lead->whatsapp_url = $number
                ? 'https://wa.me/'.$number.'?text='.rawurlencode($message)
                : null;
        });
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

        $filters = array_intersect_key($request->query(), array_flip(['q', 'status', 'campaign']));

        return redirect()->route('admin.marketing.leads.index', $filters)
            ->with('success', 'Lead updated.');
    }
}
