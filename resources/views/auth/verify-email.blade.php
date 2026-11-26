@extends('layouts.app')
@section('title', 'Verify your email')
@section('content')
<div class="mx-auto max-w-lg px-4 py-14">
    <div class="rounded-2xl border border-slate-200 bg-white p-7 shadow-sm">
        <h1 class="text-2xl font-bold text-slate-900">Verify your email address</h1>
        <p class="mt-3 text-sm leading-6 text-slate-600">We sent a verification link to your registered email address. Open that link to confirm your email.</p>
        @if (session('status'))<p role="status" class="mt-4 rounded-lg bg-emerald-50 p-3 text-sm text-emerald-800">{{ session('status') }}</p>@endif
        <form method="POST" action="{{ route('verification.send') }}" class="mt-6">@csrf
            <button class="w-full rounded-lg bg-blue-900 px-4 py-3 font-semibold text-white hover:bg-blue-800">Resend verification email</button>
        </form>
        <form method="POST" action="{{ route('logout') }}" class="mt-3">@csrf
            <button class="w-full rounded-lg border border-slate-300 px-4 py-3 font-semibold text-slate-700">Log out</button>
        </form>
    </div>
</div>
@endsection
