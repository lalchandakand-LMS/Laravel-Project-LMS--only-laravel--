@extends('layouts.admin')
@section('title', 'Coupons')
@section('heading', 'Coupons')
@section('actions')
    <div class="flex flex-wrap gap-2">
        <a href="{{ route('admin.coupons.usage') }}" class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-bold text-slate-700">Usage report</a>
        <a href="{{ route('admin.coupons.create') }}" class="rounded-lg bg-orange-600 px-4 py-2 text-sm font-bold text-white">+ New coupon</a>
    </div>
@endsection
@section('content')
<div class="mb-4 grid gap-3 sm:grid-cols-3">
    <div class="rounded-xl border border-slate-200 bg-white p-4"><p class="text-xs font-semibold uppercase text-slate-500">Coupons</p><p class="mt-1 text-2xl font-bold text-slate-900">{{ $coupons->total() }}</p></div>
    <div class="rounded-xl border border-slate-200 bg-white p-4"><p class="text-xs font-semibold uppercase text-slate-500">Reserved orders on this page</p><p class="mt-1 text-2xl font-bold text-slate-900">{{ $coupons->sum('reserved_orders_count') }}</p></div>
    <div class="rounded-xl border border-slate-200 bg-white p-4"><p class="text-xs font-semibold uppercase text-slate-500">Paid orders on this page</p><p class="mt-1 text-2xl font-bold text-slate-900">{{ $coupons->sum('paid_orders_count') }}</p></div>
</div>
<div class="overflow-x-auto rounded-2xl border border-slate-200 bg-white shadow-sm">
    <table class="w-full text-sm">
        <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
            <tr><th class="px-4 py-3">Code</th><th class="px-4 py-3">Discount</th><th class="px-4 py-3">Scope</th><th class="px-4 py-3">Usage</th><th class="px-4 py-3">Expires</th><th class="px-4 py-3">Status</th><th class="px-4 py-3 text-right">Actions</th></tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
            @forelse ($coupons as $coupon)
                <tr>
                    <td class="px-4 py-3">
                        <div class="font-mono font-bold text-slate-900">{{ $coupon->code }}</div>
                        <div class="mt-1 text-xs text-slate-500">{{ $coupon->branch?->name ?? 'All branches' }}</div>
                    </td>
                    <td class="px-4 py-3">{{ $coupon->type === 'percent' ? $coupon->value.'%' : '৳'.number_format($coupon->value, 2) }}<div class="text-xs text-slate-500">Min ৳{{ number_format($coupon->min_amount, 2) }}@if($coupon->max_discount) · Cap ৳{{ number_format($coupon->max_discount, 2) }}@endif</div></td>
                    <td class="px-4 py-3 text-xs text-slate-600">{{ count($coupon->course_ids ?? []) + count($coupon->category_ids ?? []) + count($coupon->ebook_ids ?? []) === 0 ? 'Entire cart' : count($coupon->course_ids ?? []).' courses · '.count($coupon->category_ids ?? []).' categories · '.count($coupon->ebook_ids ?? []).' e-books' }}</td>
                    <td class="px-4 py-3"><strong>{{ $coupon->reserved_orders_count }}</strong>{{ $coupon->usage_limit ? ' / '.$coupon->usage_limit : ' / ∞' }}<div class="text-xs text-slate-500">{{ $coupon->paid_orders_count }} paid · per student {{ $coupon->per_user_limit ?: '∞' }}</div></td>
                    <td class="px-4 py-3 text-slate-500">{{ optional($coupon->expires_at)->format('d M Y H:i') ?? '—' }}</td>
                    <td class="px-4 py-3"><span class="rounded-full px-2 py-0.5 text-xs font-bold {{ $coupon->is_active ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-500' }}">{{ $coupon->is_active ? 'Active' : 'Inactive' }}</span></td>
                    <td class="px-4 py-3 text-right whitespace-nowrap">
                        <a href="{{ route('admin.coupons.edit', $coupon) }}" class="font-semibold text-orange-700">Edit</a>
                        @if (! $coupon->orders()->exists())
                            <form method="POST" action="{{ route('admin.coupons.destroy', $coupon) }}" class="inline" onsubmit="return confirm('Delete coupon?')">@csrf @method('DELETE')<button class="ml-3 font-semibold text-rose-600">Delete</button></form>
                        @else
                            <span class="ml-3 text-xs text-slate-400">History locked</span>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="7" class="px-4 py-10 text-center text-slate-500">No coupons yet. Create your first coupon.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
<div class="mt-4">{{ $coupons->links() }}</div>
@endsection
