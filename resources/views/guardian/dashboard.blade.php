@extends('layouts.portal')
@section('title', 'Guardian dashboard')
@section('heading', 'Guardian dashboard')
@section('content')
@include('partials.dashboard-banner')
@php $cur = \App\Models\Setting::get('currency', '৳'); @endphp

@if (\App\Models\Setting::get('dashboard_widget_guardian_children', '1') === '1')
<div class="grid gap-5 md:grid-cols-2 xl:grid-cols-3">
    @forelse ($summary as $row)
        @php $child = $row['child']; @endphp
        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <div class="flex items-center gap-3">
                <img src="{{ $child->avatarUrl() }}" class="h-12 w-12 rounded-full" alt="">
                <div class="min-w-0">
                    <p class="truncate font-bold text-slate-900">{{ $child->name }}</p>
                    <p class="truncate text-xs text-slate-500">
                        {{ $child->branch?->name ?? 'No branch' }} · {{ $child->phone ?: $child->email }}
                    </p>
                </div>
            </div>

            <div class="mt-4">
                <div class="flex items-center justify-between text-xs font-bold text-slate-600">
                    <span>Overall progress</span><span class="text-emerald-700">{{ (int) $row['progress'] }}%</span>
                </div>
                <div class="mt-1.5 h-2 rounded-full bg-slate-100">
                    <div class="h-2 rounded-full bg-emerald-600" style="width: {{ (int) $row['progress'] }}%"></div>
                </div>
            </div>

            <div class="mt-4 grid grid-cols-2 gap-2 text-center text-xs">
                <div class="rounded-lg bg-slate-50 py-2"><p class="text-base font-black text-slate-800">{{ $row['courses'] }}</p><p class="text-slate-500">Courses</p></div>
                <div class="rounded-lg bg-slate-50 py-2"><p class="text-base font-black text-slate-800">{{ $row['avg_score'] }}%</p><p class="text-slate-500">Avg. score</p></div>
                <div class="rounded-lg bg-slate-50 py-2"><p class="text-base font-black text-slate-800">{{ $row['exams'] }}</p><p class="text-slate-500">Exams taken</p></div>
                <div class="rounded-lg bg-slate-50 py-2"><p class="text-base font-black text-slate-800">{{ $row['certificates'] }}</p><p class="text-slate-500">Certificates</p></div>
                <div class="rounded-lg bg-slate-50 py-2"><p class="text-base font-black text-slate-800">{{ $row['coaching_batches'] }}</p><p class="text-slate-500">Coaching batches</p></div>
            </div>

            @if ($row['coaching_due'] > 0)
                <p class="mt-3 rounded-lg bg-amber-50 px-3 py-2 text-xs font-bold text-amber-800">Coaching fee due: {{ $cur }}{{ number_format($row['coaching_due'],2) }}</p>
            @endif

            @if ($row['due'] > 0)
                <p class="mt-3 rounded-lg bg-amber-50 px-3 py-2 text-xs font-bold text-amber-800">Due payment: {{ $cur }}{{ number_format($row['due']) }}</p>
            @endif

            <a href="{{ route('guardian.child', $child) }}" class="mt-4 block rounded-lg bg-emerald-700 px-4 py-2 text-center text-sm font-bold text-white">Full report card</a>
            <div class="mt-2 grid grid-cols-2 gap-2">
                <a href="{{ route('guardian.child.attendance',$child) }}" class="rounded-lg border px-2 py-2 text-center text-xs font-bold">Attendance</a>
                <a href="{{ route('guardian.child.coaching-fees',$child) }}" class="rounded-lg border px-2 py-2 text-center text-xs font-bold">Coaching fees</a>
            </div>
        </div>
    @empty
        <div class="rounded-2xl border border-dashed border-slate-300 bg-white p-8 text-center text-slate-500 md:col-span-2 xl:col-span-3">
            No child is linked to your account yet. Please contact the branch office to link your child’s profile.
        </div>
    @endforelse
</div>@endif


@if (\App\Models\Setting::get('dashboard_widget_guardian_orders', '1') === '1')
<div class="mt-6 overflow-x-auto rounded-2xl border border-slate-200 bg-white shadow-sm">
    <h2 class="border-b border-slate-100 px-5 py-4 font-bold text-slate-800">Recent purchases</h2>
    <table class="w-full min-w-[620px] text-sm">
        <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
            <tr><th class="px-5 py-3">Order</th><th class="px-5 py-3">Student</th><th class="px-5 py-3">Items</th><th class="px-5 py-3">Total</th><th class="px-5 py-3">Status</th></tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
            @forelse ($orders as $order)
                <tr>
                    <td class="px-5 py-3 font-semibold text-slate-800">{{ $order->order_no }}</td>
                    <td class="px-5 py-3 text-slate-600">{{ $order->user?->name }}</td>
                    <td class="px-5 py-3 text-slate-600">{{ $order->items->count() }}</td>
                    <td class="px-5 py-3 font-bold text-slate-800">{{ $cur }}{{ number_format($order->total) }}</td>
                    <td class="px-5 py-3"><span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-bold text-slate-700">{{ ucfirst($order->status) }}</span></td>
                </tr>
            @empty
                <tr><td colspan="5" class="px-5 py-8 text-center text-slate-500">No orders yet.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>@endif

@endsection
