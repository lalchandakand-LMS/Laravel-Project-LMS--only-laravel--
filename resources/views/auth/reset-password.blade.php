@extends('layouts.app')
@section('title', 'Choose a new password')
@section('content')
<div class="mx-auto max-w-lg px-4 py-14">
    <div class="rounded-2xl border border-slate-200 bg-white p-7 shadow-sm">
        <h1 class="text-2xl font-bold text-slate-900">Choose a new password</h1>
        <p class="mt-2 text-sm text-slate-600">Use at least 10 characters with uppercase, lowercase and a number.</p>
        <form method="POST" action="{{ route('password.update') }}" class="mt-6 space-y-4">
            @csrf
            <input type="hidden" name="token" value="{{ $token }}">
            <label class="block text-sm font-semibold text-slate-700">Email
                <input type="email" name="email" value="{{ old('email', $email) }}" required autocomplete="email" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2.5">
            </label>
            <label class="block text-sm font-semibold text-slate-700">New password
                <input type="password" name="password" required autocomplete="new-password" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2.5">
            </label>
            <label class="block text-sm font-semibold text-slate-700">Confirm new password
                <input type="password" name="password_confirmation" required autocomplete="new-password" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2.5">
            </label>
            @if ($errors->any())<ul class="list-inside list-disc text-sm text-red-600">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>@endif
            <button class="w-full rounded-lg bg-blue-900 px-4 py-3 font-semibold text-white hover:bg-blue-800">Save new password</button>
        </form>
    </div>
</div>
@endsection
