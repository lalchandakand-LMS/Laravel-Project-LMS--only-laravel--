@extends('layouts.admin')
@section('title', 'Flash Sales')
@section('heading', 'Flash Sales & Discounts')
@section('actions')
    <a href="{{ route('admin.flash-sales.create') }}" class="rounded-lg bg-orange-600 px-4 py-2 text-sm font-bold text-white">+ Create flash sale</a>
@endsection
@section('content')
<div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                <tr><th class="px-5 py-3">Campaign</th><th class="px-5 py-3">Discount</th><th class="px-5 py-3">Applies to</th><th class="px-5 py-3">Schedule</th><th class="px-5 py-3">Status</th><th class="px-5 py-3 text-right">Actions</th></tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
            @forelse($flashSales as $sale)
                <tr>
                    <td class="px-5 py-3"><div class="font-bold text-slate-900">{{ $sale->name }}</div><div class="text-xs text-slate-500">{{ $sale->description }}</div></td>
                    <td class="px-5 py-3 font-semibold">{{ $sale->type === 'percent' ? number_format($sale->value, 2).'%' : '৳'.number_format($sale->value, 2) }}</td>
                    <td class="px-5 py-3"><div>{{ $sale->courses->count() }} course(s)</div><div>{{ $sale->categories->count() }} categor{{ $sale->categories->count() === 1 ? 'y' : 'ies' }}</div></td>
                    <td class="px-5 py-3 text-xs text-slate-600">{{ $sale->starts_at->format('d M Y, h:i A') }}<br>to {{ $sale->ends_at->format('d M Y, h:i A') }}</td>
                    <td class="px-5 py-3"><span class="rounded-full px-2 py-1 text-xs font-bold {{ $sale->statusLabel() === 'Running' ? 'bg-emerald-100 text-emerald-700' : ($sale->statusLabel() === 'Scheduled' ? 'bg-amber-100 text-amber-700' : 'bg-slate-100 text-slate-600') }}">{{ $sale->statusLabel() }}</span></td>
                    <td class="px-5 py-3 text-right whitespace-nowrap">
                        <a href="{{ route('admin.flash-sales.edit', $sale) }}" class="font-semibold text-orange-700">Edit</a>
                        <form method="POST" action="{{ route('admin.flash-sales.destroy', $sale) }}" class="inline" onsubmit="return confirm('Archive this flash sale?')">@csrf @method('DELETE')<button class="ml-3 font-semibold text-rose-600">Delete</button></form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" class="px-5 py-12 text-center text-slate-500">No flash sale campaigns yet.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
<div class="mt-4">{{ $flashSales->links() }}</div>
@endsection
