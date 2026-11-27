<form method="POST" action="{{ route('newsletter.subscribe') }}" class="space-y-3">
    @csrf
    <div class="grid gap-3 sm:grid-cols-2">
        <input type="text" name="name" value="{{ old('name') }}" maxlength="120" placeholder="Your name (optional)" class="w-full rounded-lg border-slate-300 text-sm">
        <input type="email" name="email" value="{{ old('email') }}" required maxlength="255" placeholder="Email address" class="w-full rounded-lg border-slate-300 text-sm">
    </div>
    <label class="flex items-start gap-2 text-xs leading-5 text-slate-600"><input type="checkbox" name="consent" value="1" required class="mt-1 rounded border-slate-300"> I agree to receive course news and learning updates by email. I can unsubscribe at any time.</label>
    <input type="text" name="website" tabindex="-1" autocomplete="off" aria-hidden="true" class="hidden">
    <button class="rounded-lg bg-orange-600 px-4 py-2 text-sm font-bold text-white hover:bg-orange-700">Subscribe to newsletter</button>
    @if(session('newsletter_status'))<p role="status" class="text-sm text-emerald-700">{{ session('newsletter_status') }}</p>@endif
    @if($errors->any())<p role="alert" class="text-sm text-rose-700">{{ $errors->first() }}</p>@endif
</form>
