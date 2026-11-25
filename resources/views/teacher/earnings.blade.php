@extends('layouts.portal')
@section('title', 'Earnings')
@section('heading', 'Course revenue & earnings')
@section('content')
@php $cur = \App\Models\Setting::get('currency', '৳'); @endphp

<div class="grid gap-4 sm:grid-cols-3">
    <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
        <p class="text-xs font-bold uppercase tracking-wide text-slate-500">Net lifetime revenue</p>
        <p class="mt-2 text-2xl font-black text-slate-900">{{ $cur }}{{ number_format($total, 2) }}</p>
        <p class="mt-1 text-xs text-slate-500">Verified payments less refunds, allocated to your courses.</p>
    </div>
    <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
        <p class="text-xs font-bold uppercase tracking-wide text-slate-500">This month</p>
        <p class="mt-2 text-2xl font-black text-slate-900">{{ $cur }}{{ number_format($months->last()['amount'] ?? 0, 2) }}</p>
    </div>
    <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
        <p class="text-xs font-bold uppercase tracking-wide text-slate-500">Ledger entries</p>
        <p class="mt-2 text-2xl font-black text-slate-900">{{ $rows->total() }}</p>
        <p class="mt-1 text-xs text-slate-500">Allocated payment and refund entries.</p>
    </div>
</div>

<div class="mt-5 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
    <h2 class="mb-4 font-bold text-slate-800">Last 6 months · net collected</h2>
    @php $max = max(1, $months->max('amount')); @endphp
    <div class="flex h-44 items-end gap-4">
        @foreach ($months as $month)
            <div class="flex flex-1 flex-col items-center gap-2">
                <span class="text-xs font-bold text-slate-600">{{ $cur }}{{ number_format($month['amount'], 2) }}</span>
                <div class="w-full rounded-t-lg bg-emerald-600" style="height: {{ max(4, (int) (max(0, $month['amount']) / $max * 130)) }}px"></div>
                <span class="text-xs text-slate-500">{{ $month['label'] }}</span>
            </div>
        @endforeach
    </div>
</div>

<div class="mt-5 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
    <h2 class="border-b border-slate-100 px-5 py-4 font-bold text-slate-800">Allocated payment history</h2>
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                <tr><th class="px-5 py-3">Student</th><th class="px-5 py-3">Course</th><th class="px-5 py-3">Method / reference</th><th class="px-5 py-3">Record</th><th class="px-5 py-3">Allocated amount</th><th class="px-5 py-3">Date</th></tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($rows as $row)
                    <tr>
                        <td class="px-5 py-3 font-semibold text-slate-800">{{ $row->user?->name }}</td>
                        <td class="px-5 py-3 text-slate-600">{{ \Illuminate\Support\Str::limit($row->course?->title, 40) }}</td>
                        <td class="px-5 py-3"><span class="font-semibold uppercase">{{ $row->gateway }}</span><p class="text-xs text-slate-500">{{ $row->reference ?: '—' }}</p></td>
                        <td class="px-5 py-3 capitalize">{{ $row->type }} · {{ $row->status }}</td>
                        <td class="px-5 py-3 font-bold {{ $row->amount < 0 ? 'text-rose-700' : 'text-emerald-700' }}">{{ $cur }}{{ number_format($row->amount, 2) }}</td>
                        <td class="px-5 py-3 text-slate-500">{{ $row->created_at->format('d M Y') }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-5 py-10 text-center text-slate-500">No verified course payments yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
<div class="mt-4">{{ $rows->links() }}</div>
@endsection
