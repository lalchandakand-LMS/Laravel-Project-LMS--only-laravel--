@extends('layouts.app')
@section('title', 'Identity verification')
@section('content')
<div class="mx-auto max-w-xl px-4 py-14">
    <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div class="bg-emerald-700 px-6 py-5 text-white"><p class="text-xs font-bold uppercase tracking-widest">Alom's English Center</p><h1 class="mt-1 text-2xl font-black">Identity verification</h1></div>
        <div class="p-6">
            <div class="flex items-center gap-4">
                <img src="{{ $user->avatarUrl() }}" alt="" class="h-20 w-20 rounded-xl object-cover">
                <div><h2 class="text-xl font-bold text-slate-900">{{ $user->name }}</h2><p class="text-sm text-slate-500">{{ $role }}</p><p class="mt-1 text-sm font-semibold text-emerald-800">{{ $identifier ?: 'ID not assigned' }}</p></div>
            </div>
            <dl class="mt-6 divide-y rounded-xl border text-sm">
                <div class="flex justify-between px-4 py-3"><dt class="text-slate-500">Branch</dt><dd class="font-semibold">{{ $user->branch?->name ?? "Alom's English Center" }}</dd></div>
                <div class="flex justify-between px-4 py-3"><dt class="text-slate-500">Profile status</dt><dd class="font-semibold capitalize">{{ str_replace('_', ' ', $status) }}</dd></div>
                <div class="flex justify-between px-4 py-3"><dt class="text-slate-500">Account</dt><dd class="font-semibold text-emerald-700">Active</dd></div>
            </dl>
            <p class="mt-5 text-xs text-slate-500">This page confirms that the identity token belongs to an active account. It does not display private contact details or documents.</p>
        </div>
    </div>
</div>
@endsection
