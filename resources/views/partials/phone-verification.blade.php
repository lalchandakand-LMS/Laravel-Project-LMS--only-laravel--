@php($phoneUser = auth()->user())
<div class="mb-5 rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
    <div class="flex flex-wrap items-center justify-between gap-2">
        <div><p class="text-sm font-bold text-slate-800">Mobile number verification</p><p class="mt-1 text-xs {{ $phoneUser->phone_verified_at ? 'text-emerald-700' : 'text-amber-700' }}">{{ $phoneUser->phone_verified_at ? 'Verified on '.$phoneUser->phone_verified_at->format('d M Y, h:i A') : ($phoneUser->phone ? 'Not verified yet · '.$phoneUser->phone : 'Add a mobile number in your profile first') }}</p></div>
        @if($phoneUser->phone_verified_at)<span class="rounded-full bg-emerald-100 px-3 py-1 text-xs font-bold text-emerald-700">Verified</span>@endif
    </div>
    @if(!$phoneUser->phone_verified_at)
        <div class="mt-3 flex flex-wrap items-center gap-2">
            <form method="POST" action="{{ route('account.phone.send') }}">@csrf<button class="rounded-lg border border-emerald-700 px-3 py-2 text-xs font-bold text-emerald-700">Send OTP to saved mobile</button></form>
            <form method="POST" action="{{ route('account.phone.verify') }}" class="flex flex-wrap gap-2">@csrf<input name="otp" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]{6}" maxlength="6" placeholder="6-digit OTP" required class="w-36 rounded-lg border px-3 py-2 text-sm"><button class="rounded-lg bg-slate-900 px-4 py-2 text-xs font-bold text-white">Verify mobile</button></form>
        </div>
        <p class="mt-2 text-xs text-slate-500">Save your correct Bangladesh mobile number first. OTP expires in 5 minutes and allows up to 5 attempts.</p>
    @endif
</div>
