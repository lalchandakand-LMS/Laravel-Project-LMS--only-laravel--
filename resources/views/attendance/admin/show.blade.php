@extends('layouts.admin')
@section('title', 'Review attendance')
@section('heading', 'Review attendance')
@section('content')
<div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"><div class="mb-4"><h2 class="text-lg font-bold">{{ $session->batch?->name }} · {{ $session->course?->title }}</h2><p class="text-sm text-slate-500">{{ $session->instructor?->name }} · {{ $session->starts_at->format('d M Y, h:i A') }} · {{ ucfirst($session->mode) }}</p></div>
<div class="mb-5 grid grid-cols-2 gap-3 sm:grid-cols-4">@foreach (['present','absent','late','leave'] as $status)<div class="rounded-xl bg-slate-50 p-3"><p class="text-xs uppercase text-slate-500">{{ ucfirst($status) }}</p><p class="text-2xl font-black">{{ $session->records->where('status',$status)->count() }}</p></div>@endforeach</div>
<div class="divide-y border-y">@foreach ($session->records as $record)<div class="flex justify-between gap-3 py-3 text-sm"><div><b>{{ $record->student?->name }}</b>@if($record->note)<p class="text-xs text-slate-500">{{ $record->note }}</p>@endif</div><span class="font-bold">{{ ucfirst($record->status) }}</span></div>@endforeach</div>
@if ($session->status === 'submitted')
<form method="POST" action="{{ route('admin.attendance.review', $session) }}" class="mt-5 space-y-3">@csrf
<label class="block text-sm font-semibold">Review note (required when returning)<textarea name="review_note" rows="3" class="mt-1 w-full rounded-xl border-slate-300">{{ old('review_note') }}</textarea></label>
<div class="flex flex-wrap gap-3"><button name="action" value="approve" class="rounded-xl bg-emerald-700 px-5 py-2.5 text-sm font-bold text-white">Approve & publish</button><button name="action" value="return" class="rounded-xl border border-amber-300 px-5 py-2.5 text-sm font-bold text-amber-800">Return to teacher</button></div></form>
@else<p class="mt-5 text-sm text-slate-600">Current status: <b>{{ ucfirst($session->status) }}</b> @if($session->review_note) — {{ $session->review_note }} @endif</p>@endif
</div>
@endsection
