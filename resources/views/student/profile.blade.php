@extends('layouts.student')
@section('title', 'Profile')
@section('heading', 'Profile settings')
@section('content')
<div class="mb-5 flex flex-wrap items-center justify-between gap-3 rounded-xl border border-emerald-200 bg-white p-4"><div><p class="font-bold text-slate-900">Your student identity card</p><p class="text-xs text-slate-500">Print or save your QR-enabled identity card.</p></div><a href="{{ route('student.id-card') }}" class="rounded-lg bg-emerald-700 px-4 py-2 text-sm font-bold text-white">View ID card</a></div>
<div class="grid gap-6 lg:grid-cols-2">
    <form method="POST" action="{{ route('student.profile.update') }}" enctype="multipart/form-data" class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        @csrf @method('PUT')
        <h3 class="font-bold text-slate-900">Personal information</h3>
        <div class="mt-4 flex items-center gap-4">
            <img src="{{ $user->avatarUrl() }}" class="h-16 w-16 rounded-full object-cover" alt="">
            <div class="min-w-0 flex-1">
                <input type="file" name="avatar" accept="image/png,image/jpeg,image/webp,image/gif" class="w-full text-sm">
                <label class="mt-2 block text-xs font-semibold text-slate-600">Or paste a profile photo URL</label>
                <input type="url" name="avatar_url" value="{{ old('avatar_url', str_starts_with((string) $user->avatar, 'http') ? $user->avatar : '') }}" placeholder="https://example.com/profile.jpg" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
            </div>
        </div>
        <div class="mt-4 space-y-3">
            <div>
                <label class="mb-1 block text-sm font-semibold text-slate-700">Name</label>
                <input name="name" value="{{ old('name', $user->name) }}" required class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm">
            </div>
            <div>
                <label class="mb-1 block text-sm font-semibold text-slate-700">Email</label>
                <input value="{{ $user->email }}" disabled class="w-full rounded-lg border border-slate-200 bg-slate-50 px-3 py-2.5 text-sm text-slate-600">
            </div>
            <div>
                <label class="mb-1 block text-sm font-semibold text-slate-700">Phone</label>
                <input name="phone" value="{{ old('phone', $user->phone) }}" class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm">
            </div>
            <div>
                <label class="mb-1 block text-sm font-semibold text-slate-700">Bio</label>
                <textarea name="bio" rows="3" class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm">{{ old('bio', $user->bio) }}</textarea>
            </div>
        </div>
        <div class="mt-4 rounded-xl border border-slate-200 bg-slate-50 p-4">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <div><p class="text-sm font-bold text-slate-800">Mobile number verification</p><p class="mt-1 text-xs {{ $user->phone_verified_at ? 'text-emerald-700' : 'text-amber-700' }}">{{ $user->phone_verified_at ? 'Verified on '.$user->phone_verified_at->format('d M Y, h:i A') : 'Not verified yet' }}</p></div>
                @if($user->phone_verified_at)<span class="rounded-full bg-emerald-100 px-3 py-1 text-xs font-bold text-emerald-700">Verified</span>@endif
            </div>
            @if(!$user->phone_verified_at)
                <form method="POST" action="{{ route('account.phone.send') }}" class="mt-3">@csrf<button class="rounded-lg border border-emerald-700 px-3 py-2 text-xs font-bold text-emerald-700">Send OTP to saved mobile</button></form>
                <form method="POST" action="{{ route('account.phone.verify') }}" class="mt-3 flex flex-wrap gap-2">@csrf<input name="otp" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]{6}" maxlength="6" placeholder="6-digit OTP" required class="w-36 rounded-lg border px-3 py-2 text-sm"><button class="rounded-lg bg-slate-900 px-4 py-2 text-xs font-bold text-white">Verify mobile</button></form>
                <p class="mt-2 text-xs text-slate-500">Save your correct Bangladesh mobile number first. OTP expires in 5 minutes and allows up to 5 attempts.</p>
            @endif
        </div>
        <button class="mt-5 rounded-lg bg-emerald-700 px-5 py-2.5 text-sm font-bold text-white">Save changes</button>
    </form>

    <form method="POST" action="{{ route('student.password.update') }}" class="h-fit rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        @csrf @method('PUT')
        <h3 class="font-bold text-slate-900">Change password</h3>
        <div class="mt-4 space-y-3">
            <div>
                <label class="mb-1 block text-sm font-semibold text-slate-700">Current password</label>
                <input type="password" name="current_password" required class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm">
            </div>
            <div>
                <label class="mb-1 block text-sm font-semibold text-slate-700">New password</label>
                <input type="password" name="password" required class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm">
            </div>
            <div>
                <label class="mb-1 block text-sm font-semibold text-slate-700">Confirm new password</label>
                <input type="password" name="password_confirmation" required class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm">
            </div>
        </div>
        <button class="mt-5 rounded-lg bg-slate-900 px-5 py-2.5 text-sm font-bold text-white">Update password</button>
    </form>
</div>
@endsection
