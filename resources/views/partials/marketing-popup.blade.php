@php
    $popupEnabled = \App\Models\Setting::get('marketing_popup_enabled', '0') === '1';
    $popupPrivate = auth()->check() || request()->is('admin', 'admin/*', 'my', 'my/*', 'teacher', 'teacher/*', 'guardian', 'guardian/*', 'branch', 'branch/*', 'cart', 'checkout', 'checkout/*', 'messages', 'messages/*', 'notifications', 'notifications/*');
    $popupDisplay = \App\Models\Setting::get('marketing_popup_display', 'home');
    $popupEligible = $popupDisplay === 'public' || request()->routeIs('home');
@endphp
@if($popupEnabled && !$popupPrivate && $popupEligible)
<div id="aec-marketing-popup" class="fixed inset-0 z-[10000] hidden items-center justify-center bg-slate-950/60 p-4" role="dialog" aria-modal="true" aria-labelledby="aec-popup-title">
    <div class="relative w-full max-w-lg rounded-2xl bg-white p-6 shadow-2xl">
        <button type="button" id="aec-popup-close" class="absolute right-3 top-3 grid h-9 w-9 place-items-center rounded-full bg-slate-100 text-xl text-slate-600" aria-label="Close popup">×</button>
        <h2 id="aec-popup-title" class="pr-10 text-2xl font-black text-slate-900">{{ \App\Models\Setting::get('marketing_popup_title', 'Get course information') }}</h2>
        <p class="mt-2 text-sm leading-6 text-slate-600">{{ \App\Models\Setting::get('marketing_popup_body', 'Leave your details and our team will help you choose the right course.') }}</p>
        <form method="POST" action="{{ route('marketing.popup.capture') }}" class="mt-5 space-y-3">
            @csrf
            <input name="name" required maxlength="120" placeholder="Your name" class="w-full rounded-lg border-slate-300 text-sm">
            <input type="email" name="email" required maxlength="255" placeholder="Email address" class="w-full rounded-lg border-slate-300 text-sm">
            <input name="phone" maxlength="40" placeholder="Phone number (optional)" class="w-full rounded-lg border-slate-300 text-sm">
            <label class="flex items-start gap-2 text-xs leading-5 text-slate-600"><input type="checkbox" name="consent" value="1" required class="mt-1 rounded border-slate-300"> I agree that the center may contact me about courses and admission. I can request that my details be removed.</label>
            <input type="text" name="website" tabindex="-1" autocomplete="off" aria-hidden="true" class="hidden">
            <button class="w-full rounded-lg bg-orange-600 px-4 py-3 text-sm font-bold text-white">{{ \App\Models\Setting::get('marketing_popup_button_text', 'Request information') }}</button>
            @if(session('popup_status'))<p role="status" class="text-sm text-emerald-700">{{ session('popup_status') }}</p>@endif
            @if($errors->has('name') || $errors->has('email') || $errors->has('consent'))<p role="alert" class="text-sm text-rose-700">{{ $errors->first() }}</p>@endif
        </form>
    </div>
</div>
<script>
(function(){
    var modal=document.getElementById('aec-marketing-popup'); if(!modal)return;
    var key='aec-marketing-popup-seen', frequency=@json(\App\Models\Setting::get('marketing_popup_frequency','week'));
    var now=Date.now(), previous=Number(localStorage.getItem(key)||0), hasErrors=@json($errors->has('name') || $errors->has('email') || $errors->has('consent'));
    var interval=frequency==='session' ? Infinity : (frequency==='day' ? 86400000 : 604800000);
    if(!hasErrors && frequency==='session' && sessionStorage.getItem(key)==='1')return;
    if(!hasErrors && frequency!=='session' && previous && now-previous<interval)return;
    var close=function(){modal.classList.add('hidden');modal.classList.remove('flex');};
    document.getElementById('aec-popup-close').addEventListener('click',close);
    modal.addEventListener('click',function(e){if(e.target===modal)close();});
    document.addEventListener('keydown',function(e){if(e.key==='Escape')close();});
    window.setTimeout(function(){
        modal.classList.remove('hidden');modal.classList.add('flex');
        if(!hasErrors){if(frequency==='session')sessionStorage.setItem(key,'1');else localStorage.setItem(key,String(Date.now()));}
        var first=modal.querySelector('input[name="name"]'); if(first)first.focus();
    },{{ (int) \App\Models\Setting::get('marketing_popup_delay','8') }}*1000);
})();
</script>
@endif
