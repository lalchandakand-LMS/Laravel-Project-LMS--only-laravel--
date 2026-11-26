@extends('layouts.admin')
@section('title', 'Popup Builder')
@section('heading', 'Marketing Popup Builder')
@section('content')
<div class="mx-auto max-w-7xl space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-3"><p class="text-sm text-slate-600">Create scheduled offer and admission popups for public website visitors.</p><a href="{{ route('admin.marketing.popups.create') }}" class="rounded-xl bg-orange-600 px-4 py-2.5 text-sm font-bold text-white">+ Create Popup</a></div>
    <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div class="overflow-x-auto"><table class="min-w-full text-left text-sm">
            <thead class="bg-slate-50 text-xs uppercase text-slate-500"><tr><th class="p-3">Popup</th><th class="p-3">Audience / Display</th><th class="p-3">Schedule</th><th class="p-3">Performance</th><th class="p-3">Actions</th></tr></thead>
            <tbody class="divide-y divide-slate-100">
                @forelse($popups as $popup)
                    <tr class="align-top"><td class="p-3"><p class="font-bold text-slate-900">{{ $popup->title }}</p><span class="mt-2 inline-flex rounded-full px-2 py-1 text-[10px] font-bold uppercase {{ $popup->status === 'active' ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-100 text-slate-600' }}">{{ $popup->status }}</span></td>
                    <td class="p-3 text-xs text-slate-600">{{ ucfirst($popup->target_audience) }}<p class="mt-1">{{ $popup->display_on === 'homepage_only' ? 'Homepage only' : 'All public pages' }}</p><p class="mt-1">{{ str_replace('_',' ',$popup->frequency) }}</p></td>
                    <td class="p-3 text-xs text-slate-600">{{ $popup->starts_at?->format('d M Y H:i') ?? 'Immediately' }}<p class="mt-1">Until {{ $popup->ends_at?->format('d M Y H:i') ?? 'disabled' }}</p><p class="mt-1">Priority: {{ $popup->priority }}</p></td>
                    <td class="p-3 text-xs"><p><b>{{ number_format($popup->impressions) }}</b> impressions</p><p class="mt-1"><b>{{ number_format($popup->clicks) }}</b> clicks</p><p class="mt-1 text-slate-500">CTR: {{ $popup->impressions > 0 ? number_format(($popup->clicks / $popup->impressions) * 100, 1) : '0.0' }}%</p></td>
                    <td class="p-3"><div class="flex gap-3"><a href="{{ route('admin.marketing.popups.edit',$popup) }}" class="font-semibold text-blue-700">Edit</a><form method="POST" action="{{ route('admin.marketing.popups.destroy',$popup) }}" onsubmit="return confirm('Archive this popup?')">@csrf @method('DELETE')<button class="font-semibold text-rose-700">Archive</button></form></div></td></tr>
                @empty
                    <tr><td colspan="5" class="p-10 text-center text-sm text-slate-500">No popups created yet.</td></tr>
                @endforelse
            </tbody>
        </table></div>
        <div class="border-t border-slate-100 p-4">{{ $popups->links() }}</div>
    </div>
</div>
@endsection
