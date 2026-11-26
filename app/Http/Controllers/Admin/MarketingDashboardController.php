<?php

namespace App\\Http\\Controllers\\Admin;

use App\\Http\\Controllers\\Controller;
use App\\Models\\MarketingCampaign;
use App\\Models\\MarketingLead;
use Carbon\\Carbon;
use Illuminate\\Http\\Request;

class MarketingDashboardController extends Controller
{
    public function index(Request $request)
    {
        $totalLeads = MarketingLead::count();
        $convertedLeads = MarketingLead::where('status', 'converted')->count();
        $newLeads = MarketingLead::where('status', 'new')->count();
        $conversionRate = $totalLeads > 0 ? round(($convertedLeads / $totalLeads) * 100, 1) : 0;

        $campaigns = MarketingCampaign::query()
            ->withCount('leads')
            ->withCount(['leads as converted_leads_count' => fn ($query) => $query->where('status', 'converted')])
            ->orderByDesc('leads_count')
            ->orderBy('name')
            ->limit(8)
            ->get();

        $sources = MarketingLead::query()
            ->selectRaw('utm_source as source, COUNT(*) as leads_count')
            ->whereNotNull('utm_source')
            ->where('utm_source', '!=', '')
            ->groupBy('utm_source')
            ->orderByDesc('leads_count')
            ->limit(6)
            ->get();

        $from = now()->subDays(13)->startOfDay();
        $dailyCounts = MarketingLead::query()
            ->where('created_at', '>=', $from)
            ->get(['created_at'])
            ->groupBy(fn ($lead) => $lead->created_at->format('Y-m-d'))
            ->map(fn ($group) => $group->count());

        $dailyLeads = collect();
        for ($day = $from->copy(); $day <= now()->startOfDay(); $day->addDay()) {
            $key = $day->format('Y-m-d');
            $dailyLeads->push([
                'date' => $day->format('d M'),
                'count' => (int) ($dailyCounts[$key] ?? 0),
            ]);
        }

        return view('admin.marketing.dashboard', compact(
            'totalLeads',
            'convertedLeads',
            'newLeads',
            'conversionRate',
            'campaigns',
            'sources',
            'dailyLeads'
        ));
    }
}
