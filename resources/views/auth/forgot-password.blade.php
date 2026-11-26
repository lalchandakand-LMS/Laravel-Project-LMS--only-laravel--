@extends('layouts.app')
@section('title', 'Forgot password')
@section('content')
<div class="mx-auto max-w-lg px-4 py-14">
    <div class="rounded-2xl border border-slate-200 bg-white p-7 shadow-sm">
        <h1 class="text-2xl font-bold text-slate-900">Reset your password</h1>
        <p class="mt-2 text-sm text-slate-600">Enter the email address on your account. We will send a secure, time-limited reset link.</p>
        @if (session('status'))<p role="status" class="mt-4 rounded-lg bg-emerald-50 p-3 text-sm text-emerald-800">{{ session('status') }}</p>@endif
        <form method="POST" action="{{ route('password.email') }}" class="mt-6 space-y-4">
            @csrf
            <label class="block text-sm font-semibold text-slate-700">Email address
                <input type="email" name="email" value="{{ old('email') }}" required autocomplete="email" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2.5">
            </label>
            @error('email')<p class="text-sm text-red-600">{{ $message }}</p>@enderror
            <button class="w-full rounded-lg bg-blue-900 px-4 py-3 font-semibold text-white hover:bg-blue-800">Send reset link</button>
        </form>
        <p class="mt-5 text-center text-sm"><a href="{{ route('login') }}" class="font-semibold text-blue-800">Back to login</a></p>
    </div>
</div>
@endsection
