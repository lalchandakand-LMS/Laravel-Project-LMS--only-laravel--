<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MarketingCampaign;
use App\Models\MarketingLead;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Http\Request;

class MarketingDashboardController extends Controller
{
    public function index(Request $request)
    {
        $totalLeads = MarketingLead::count();
        $convertedLeads = MarketingLead::where('status', 'converted')->count();
        $newLeads = MarketingLead::where('status', 'new')->count();
        $conversionRate = $totalLeads > 0 ? round(($convertedLeads / $totalLeads) * 100, 1) : 0;

        $campaigns = MarketingCampaign::query()
            ->withCount(['leads', 'orders'])
            ->addSelect(['verified_revenue' => Payment::query()
                ->join('orders', 'orders.id', '=', 'payments.order_id')
                ->selectRaw('COALESCE(SUM(payments.amount), 0)')
                ->whereColumn('orders.utm_campaign', 'marketing_campaigns.slug')
                ->where('payments.status', 'verified')])
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
        ) + [
            'campaignCount' => MarketingCampaign::count(),
            'activeCampaignCount' => MarketingCampaign::where('status', 'active')->count(),
            'attributedOrderCount' => Order::whereNotNull('utm_campaign')->count(),
            'verifiedRevenue' => Payment::query()
                ->join('orders', 'orders.id', '=', 'payments.order_id')
                ->whereNotNull('orders.utm_campaign')
                ->where('payments.status', 'verified')
                ->sum('payments.amount'),
        ]);
    }
}
