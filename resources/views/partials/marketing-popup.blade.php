@if($popup)
<div id="aec-marketing-popup" class="fixed inset-0 z-[9990] flex items-center justify-center bg-slate-950/60 p-4" role="dialog" aria-modal="true" aria-labelledby="aec-popup-title" data-popup-id="{{ $popup->id }}" data-frequency="{{ $popup->frequency }}" data-impression-url="{{ route('marketing.popups.impression',$popup) }}" data-click-url="{{ route('marketing.popups.click',$popup) }}">
    <div class="relative max-h-[90vh] w-full max-w-lg overflow-y-auto rounded-2xl bg-white shadow-2xl">
        <button id="aec-popup-close" type="button" aria-label="Close offer" class="absolute right-3 top-3 z-10 rounded-full bg-white/90 px-3 py-1 text-2xl text-slate-600 shadow">×</button>
        @if($popup->image_url)<img src="{{ $popup->image_url }}" alt="{{ $popup->title }}" class="max-h-64 w-full object-cover">@endif
        <div class="p-6"><h2 id="aec-popup-title" class="text-xl font-black text-slate-900">{{ $popup->title }}</h2>
            @if($popup->body)<p class="mt-3 whitespace-pre-line text-sm leading-6 text-slate-600">{{ $popup->body }}</p>@endif
            @if($popup->cta_label && $popup->cta_url)<a id="aec-popup-cta" href="{{ $popup->cta_url }}" class="mt-5 inline-flex rounded-xl bg-orange-600 px-5 py-3 text-sm font-bold text-white hover:bg-orange-700">{{ $popup->cta_label }}</a>@endif
        </div>
    </div>
</div>
<script>
(function () {
    var popup = document.getElementById('aec-marketing-popup');
    if (!popup) return;
    var id = popup.dataset.popupId;
    var frequency = popup.dataset.frequency;
    var sessionKey = 'aec_marketing_popup_' + id;
    if (frequency === 'once_per_session') {
        try {
            if (window.sessionStorage.getItem(sessionKey)) {
                popup.remove();
                return;
            }
            window.sessionStorage.setItem(sessionKey, '1');
        } catch (error) {}
    }

    function analyticsConsent() {
        try {
            var consent = JSON.parse(window.localStorage.getItem('aec_cookie_consent_v1') || 'null');
            return !!(consent && consent.version === 1 && consent.analytics === true);
        } catch (error) {
            return false;
        }
    }

    function track(url) {
        if (!analyticsConsent()) return;
        var token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
        if (!token) return;
        fetch(url, {
            method: 'POST',
            keepalive: true,
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': token, 'Accept': 'application/json' },
            body: JSON.stringify({ analytics_consent: true })
        }).catch(function () {});
    }

    var impressionTracked = false;
    function trackImpression() {
        if (!impressionTracked && analyticsConsent()) {
            track(popup.dataset.impressionUrl);
            impressionTracked = true;
        }
    }
    trackImpression();
    window.addEventListener('aec:consent-updated', trackImpression);
    document.getElementById('aec-popup-close')?.addEventListener('click', function () { popup.remove(); });
    document.getElementById('aec-popup-cta')?.addEventListener('click', function () { track(popup.dataset.clickUrl); });
})();
</script>
@endif
