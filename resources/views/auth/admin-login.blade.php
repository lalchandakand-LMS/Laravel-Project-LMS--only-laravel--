@extends('layouts.app')
@section('title', 'Admin Login')
@section('content')
<div class="mx-auto max-w-md px-4 py-16">
    <div class="rounded-2xl border border-slate-200 bg-white p-8 shadow-sm">
        <div class="mb-6 flex items-center gap-3">
            <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-slate-900 text-white">🔒</div>
            <div>
                <h1 class="text-2xl font-bold text-slate-900">Admin portal</h1>
                <p class="text-sm text-slate-500">Authorized administrators only</p>
            </div>
        </div>
        <form method="POST" action="{{ route('admin.login.store') }}" class="space-y-4">
            @csrf
            <div>
                <label for="email" class="mb-1 block text-sm font-semibold text-slate-700">Admin email</label>
                <input id="email" type="email" name="email" value="{{ old('email') }}" autocomplete="username" required autofocus
                    class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm focus:border-slate-700 focus:outline-none focus:ring-2 focus:ring-slate-100">
                @error('email')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="password" class="mb-1 block text-sm font-semibold text-slate-700">Password</label>
                <input id="password" type="password" name="password" autocomplete="current-password" required
                    class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm focus:border-slate-700 focus:outline-none focus:ring-2 focus:ring-slate-100">
                @error('password')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
            </div>
            <label class="flex items-center gap-2 text-sm text-slate-600">
                <input type="checkbox" name="remember" value="1" class="rounded border-slate-300"> Remember this device
            </label>
            <button type="submit" class="w-full rounded-lg bg-slate-900 px-4 py-3 font-semibold text-white hover:bg-slate-800">Secure Admin Login</button>
        </form>
        <div class="mt-6 border-t border-slate-100 pt-4 text-center text-sm">
            <a href="{{ route('password.request') }}" class="font-semibold text-slate-700 hover:underline">Forgot password?</a>
            <span class="mx-2 text-slate-300">·</span>
            <a href="{{ route('login') }}" class="text-slate-500 hover:underline">Learner login</a>
        </div>
        <p class="mt-5 text-xs leading-5 text-slate-500">Admin access is restricted by server-side role authorization. Public registration cannot create administrator accounts.</p>
    </div>
</div>
@endsection
