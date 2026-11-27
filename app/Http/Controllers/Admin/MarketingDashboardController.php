<?php

namespace App\\Http\\Controllers\\Admin;

use App\\Http\\Controllers\\Controller;
use App\\Models\\MarketingCampaign;
use App\\Models\\MarketingLead;
use App\\Models\\Order;
use App\\Models\\Payment;
use Illuminate\\Http\\Request;
use Illuminate\\Validation\\ValidationException;

class MarketingDashboardController extends Controller
{
    public function index(Request $request)
    {
        $validated = $request->validate([
            'from' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:today'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
        ]);

        $from = isset($validated['from'])
            ? now()->parse($validated['from'])->startOfDay()
            : now()->subDays(29)->startOfDay();
        $to = isset($validated['to'])
            ? now()->parse($validated['to'])->endOfDay()
            : now()->endOfDay();

        if ($from->diffInDays($to) > 30) {
            throw ValidationException::withMessages([
                'to' => 'Choose a reporting period of 31 days or less.',
            ]);
        }

        $period = [$from, $to];
        $leads = MarketingLead::query()->whereBetween('created_at', $period);
        $totalLeads = (clone $leads)->count();
        $convertedLeads = (clone $leads)->where('status', 'converted')->count();
        $newLeads = (clone $leads)->where('status', 'new')->count();
        $conversionRate = $totalLeads > 0 ? round(($convertedLeads / $totalLeads) * 100, 1) : 0;

        $campaigns = MarketingCampaign::query()
            ->withCount([
                'leads' => fn ($query) => $query->whereBetween('created_at', $period),
                'orders' => fn ($query) => $query->whereBetween('orders.created_at', $period),
            ])
            ->addSelect(['verified_revenue' => Payment::query()
                ->join('orders', 'orders.id', '=', 'payments.order_id')
                ->selectRaw('COALESCE(SUM(payments.amount), 0)')
                ->whereColumn('orders.utm_campaign', 'marketing_campaigns.slug')
                ->where('payments.status', 'verified')
                ->whereBetween('payments.verified_at', $period)])
            ->withCount(['leads as converted_leads_count' => fn ($query) => $query
                ->whereBetween('created_at', $period)
                ->where('status', 'converted')])
            ->orderByDesc('leads_count')
            ->orderBy('name')
            ->limit(8)
            ->get();

        $sources = MarketingLead::query()
            ->selectRaw('utm_source as source, COUNT(*) as leads_count')
            ->whereBetween('created_at', $period)
            ->whereNotNull('utm_source')
            ->where('utm_source', '!=', '')
            ->groupBy('utm_source')
            ->orderByDesc('leads_count')
            ->limit(6)
            ->get();

        $dailyCounts = MarketingLead::query()
            ->whereBetween('created_at', $period)
            ->get(['created_at'])
            ->groupBy(fn ($lead) => $lead->created_at->format('Y-m-d'))
            ->map(fn ($group) => $group->count());

        $dailyLeads = collect();
        for ($day = $from->copy()->startOfDay(); $day <= $to->copy()->startOfDay(); $day->addDay()) {
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
            'dailyLeads',
            'from',
            'to'
        ) + [
            'campaignCount' => MarketingCampaign::count(),
            'activeCampaignCount' => MarketingCampaign::where('status', 'active')->count(),
            'attributedOrderCount' => Order::query()
                ->whereNotNull('utm_campaign')
                ->whereBetween('created_at', $period)
                ->count(),
            'verifiedRevenue' => Payment::query()
                ->join('orders', 'orders.id', '=', 'payments.order_id')
                ->whereNotNull('orders.utm_campaign')
                ->where('payments.status', 'verified')
                ->whereBetween('payments.verified_at', $period)
                ->sum('payments.amount'),
        ]);
    }
}
