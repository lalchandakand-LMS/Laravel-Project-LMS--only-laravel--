@extends('layouts.admin')
@section('title', 'Edit Enrollment')
@section('heading', 'Edit Enrollment')
@section('content')
<div class="mx-auto max-w-3xl rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
    <div class="mb-5"><h2 class="text-lg font-bold">{{ $enrollment->user?->name }} — {{ $enrollment->course?->title }}</h2><p class="text-sm text-slate-500">Student and course identity are fixed to protect payment and learning history.</p></div>
    <form method="POST" action="{{ route('admin.enrollments.update', $enrollment) }}" class="grid gap-4 sm:grid-cols-2">
        @csrf @method('PUT')
        <label class="text-sm font-semibold">Batch<select name="batch_id" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2"><option value="">No batch</option>@foreach ($batches as $batch)<option value="{{ $batch->id }}" @selected(old('batch_id', $enrollment->batch_id) == $batch->id)>{{ $batch->name }} ({{ ucfirst($batch->status) }})</option>@endforeach</select></label>
        <label class="text-sm font-semibold">Amount paid (৳)<input type="number" name="price_paid" min="0" step="0.01" required value="{{ old('price_paid', $enrollment->price_paid) }}" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2"></label>
        <label class="text-sm font-semibold">Status<select name="status" required class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2">@foreach ($statuses as $status)<option value="{{ $status }}" @selected(old('status', $enrollment->status) === $status)>{{ ucfirst($status) }}</option>@endforeach</select></label>
        <label class="text-sm font-semibold">Enrollment date<input type="date" name="enrolled_at" required value="{{ old('enrolled_at', $enrollment->enrolled_at?->toDateString()) }}" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2"></label>
        <label class="text-sm font-semibold">Expiry date<input type="date" name="expires_at" value="{{ old('expires_at', $enrollment->expires_at?->toDateString()) }}" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2"></label>
        <label class="text-sm font-semibold sm:col-span-2">Admin note<textarea name="admin_note" rows="3" maxlength="5000" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2">{{ old('admin_note', $enrollment->admin_note) }}</textarea></label>
        <div class="flex gap-2 sm:col-span-2"><button class="rounded-lg bg-orange-600 px-5 py-2.5 font-bold text-white">Save changes</button><a href="{{ route('admin.enrollments.show', $enrollment) }}" class="rounded-lg border border-slate-300 px-5 py-2.5 font-semibold">Cancel</a></div>
    </form>
</div>
@endsection
