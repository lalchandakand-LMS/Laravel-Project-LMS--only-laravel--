@extends('layouts.app')
@section('title', 'Verify Admin Sign-in')
@section('content')
<div class="mx-auto max-w-md px-4 py-16">
    <div class="rounded-2xl border border-slate-200 bg-white p-8 shadow-sm">
        <div class="mb-6">
            <h1 class="text-2xl font-bold text-slate-900">Verify administrator sign-in</h1>
            <p class="mt-2 text-sm leading-6 text-slate-600">Enter the six-digit one-time code sent to the administrator email address. The code expires after five minutes.</p>
        </div>
        @if (session('success'))
            <div class="mb-4 rounded-lg bg-emerald-50 p-3 text-sm text-emerald-800">{{ session('success') }}</div>
        @endif
        <form method="POST" action="{{ route('admin.login.verify.store') }}" class="space-y-4">
            @csrf
            <div>
                <label for="code" class="mb-1 block text-sm font-semibold text-slate-700">One-time verification code</label>
                <input id="code" type="text" name="code" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]{6}" maxlength="6" required autofocus
                    class="w-full rounded-lg border border-slate-300 px-3 py-3 text-center text-2xl tracking-[0.5em] focus:border-slate-700 focus:outline-none focus:ring-2 focus:ring-slate-100">
                @error('code')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                @error('email')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
            </div>
            <button type="submit" class="w-full rounded-lg bg-slate-900 px-4 py-3 font-semibold text-white hover:bg-slate-800">Verify and continue</button>
        </form>
        <form method="POST" action="{{ route('admin.login.resend') }}" class="mt-4 text-center">
            @csrf
            <button type="submit" class="text-sm font-semibold text-slate-700 hover:underline">Resend code</button>
        </form>
        <p class="mt-6 text-center text-xs text-slate-500"><a href="{{ route('admin.login') }}" class="hover:underline">Cancel and return to Admin login</a></p>
    </div>
</div>
@endsection
