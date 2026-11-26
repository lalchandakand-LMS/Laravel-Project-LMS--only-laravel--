@php
    $gaMeasurementId = \App\Models\Setting::get('ga_measurement_id');
    $metaPixelId = \App\Models\Setting::get('meta_pixel_id');
@endphp
<div id="aec-cookie-consent"
     class="fixed inset-x-3 bottom-3 z-[10000] mx-auto max-w-3xl rounded-2xl border border-slate-200 bg-white p-5 shadow-2xl"
     role="dialog" aria-modal="false" aria-labelledby="aec-cookie-title"
     data-ga-id="{{ $gaMeasurementId }}"
     data-meta-pixel-id="{{ $metaPixelId }}"
     hidden>
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div class="max-w-2xl">
            <h2 id="aec-cookie-title" class="text-base font-extrabold text-slate-900">Your privacy choices</h2>
            <p class="mt-1 text-sm leading-relaxed text-slate-600">Essential storage keeps this website working. With your permission, Analytics cookies help us understand website use and Marketing cookies help measure advertising. Optional trackers stay off until you choose.</p>
            <p class="mt-2 text-xs text-slate-500">You can change or withdraw your choice later using Cookie settings. Removing existing third-party cookies may also require your browser settings.</p>
        </div>
        <button id="aec-cookie-close" type="button" aria-label="Close cookie preferences" class="rounded-lg px-2 py-1 text-xl text-slate-500 hover:bg-slate-100">×</button>
    </div>
    <div id="aec-cookie-preferences" class="mt-4 hidden rounded-xl bg-slate-50 p-4">
        <label class="flex items-center justify-between gap-3 py-2 text-sm"><span><b>Essential</b><small class="block text-xs text-slate-500">Required for core site functions; always active.</small></span><input type="checkbox" checked disabled class="rounded border-slate-300"></label>
        <label class="flex items-center justify-between gap-3 border-t border-slate-200 py-2 text-sm"><span><b>Analytics</b><small class="block text-xs text-slate-500">Google Analytics 4 measurement.</small></span><input id="aec-consent-analytics" type="checkbox" class="rounded border-slate-300 text-orange-600"></label>
        <label class="flex items-center justify-between gap-3 border-t border-slate-200 py-2 text-sm"><span><b>Marketing</b><small class="block text-xs text-slate-500">Meta Pixel and advertising conversion measurement.</small></span><input id="aec-consent-marketing" type="checkbox" class="rounded border-slate-300 text-orange-600"></label>
        <button id="aec-cookie-save" type="button" class="mt-3 rounded-lg bg-slate-900 px-4 py-2 text-sm font-bold text-white">Save my choices</button>
    </div>
    <div id="aec-cookie-actions" class="mt-4 flex flex-wrap gap-2">
        <button id="aec-cookie-accept" type="button" class="rounded-lg bg-orange-600 px-4 py-2 text-sm font-bold text-white">Accept all</button>
        <button id="aec-cookie-reject" type="button" class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-bold text-slate-700">Reject optional</button>
        <button id="aec-cookie-customize" type="button" class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-bold text-slate-700">Customize</button>
    </div>
</div>
<script>
(function () {
    var panel = document.getElementById('aec-cookie-consent');
    if (!panel) return;

    var gaId = panel.dataset.gaId || '';
    var pixelId = panel.dataset.metaPixelId || '';
    if (!gaId && !pixelId) return;

    var storageKey = 'aec_cookie_consent_v1';
    var preferences = document.getElementById('aec-cookie-preferences');
    var actions = document.getElementById('aec-cookie-actions');
    var analyticsInput = document.getElementById('aec-consent-analytics');
    var marketingInput = document.getElementById('aec-consent-marketing');
    var consent = null;

    function readConsent() {
        try {
            var value = JSON.parse(window.localStorage.getItem(storageKey) || 'null');
            return value && value.version === 1
                ? { analytics: value.analytics === true, marketing: value.marketing === true }
                : null;
        } catch (error) {
            return null;
        }
    }

    function saveConsent(value) {
        consent = { analytics: value.analytics === true, marketing: value.marketing === true };
        try {
            window.localStorage.setItem(storageKey, JSON.stringify({
                version: 1,
                analytics: consent.analytics,
                marketing: consent.marketing,
                savedAt: new Date().toISOString()
            }));
        } catch (error) {
            // Keep this page's selection active even if browser storage is unavailable.
        }
        panel.hidden = true;
        applyConsent();
    }

    function loadAnalytics() {
        if (!gaId || window.__aecGaLoaded) return;
        window.__aecGaLoaded = true;
        window.dataLayer = window.dataLayer || [];
        window.gtag = window.gtag || function () { window.dataLayer.push(arguments); };
        window.gtag('consent', 'default', { analytics_storage: 'denied', ad_storage: 'denied', ad_user_data: 'denied', ad_personalization: 'denied' });
        window.gtag('consent', 'update', { analytics_storage: 'granted' });
        window.gtag('js', new Date());
        window.gtag('config', gaId, { anonymize_ip: true });
        var script = document.createElement('script');
        script.async = true;
        script.src = 'https://www.googletagmanager.com/gtag/js?id=' + encodeURIComponent(gaId);
        document.head.appendChild(script);
    }

    function loadMarketing() {
        if (!pixelId || window.__aecMetaLoaded) return;
        window.__aecMetaLoaded = true;
        !function (f, b, e, v, n, t, s) {
            if (f.fbq) return;
            n = f.fbq = function () { n.callMethod ? n.callMethod.apply(n, arguments) : n.queue.push(arguments); };
            if (!f._fbq) f._fbq = n;
            n.push = n; n.loaded = true; n.version = '2.0'; n.queue = [];
            t = b.createElement(e); t.async = true; t.src = v;
            s = b.getElementsByTagName(e)[0]; s.parentNode.insertBefore(t, s);
        }(window, document, 'script', 'https://connect.facebook.net/en_US/fbevents.js');
        window.fbq('init', pixelId);
        window.fbq('track', 'PageView');
    }

    function sendPendingConversions() {
        var event = window.aecPurchaseEvent;
        if (!event) return;
        var transactionId = String(event.transaction_id || '');
        if (!transactionId) return;
        if (consent && consent.analytics && window.gtag && !window.__aecGaPurchaseSent) {
            var gaKey = 'aec_ga_purchase_' + transactionId;
            try { if (window.localStorage.getItem(gaKey)) return; } catch (error) {}
            window.gtag('event', 'purchase', event);
            window.__aecGaPurchaseSent = true;
            try { window.localStorage.setItem(gaKey, '1'); } catch (error) {}
        }
        if (consent && consent.marketing && window.fbq && !window.__aecMetaPurchaseSent) {
            var metaKey = 'aec_meta_purchase_' + transactionId;
            try { if (window.localStorage.getItem(metaKey)) return; } catch (error) {}
            window.fbq('track', 'Purchase', { value: event.value, currency: event.currency });
            window.__aecMetaPurchaseSent = true;
            try { window.localStorage.setItem(metaKey, '1'); } catch (error) {}
        }
    }

    function applyConsent() {
        if (!consent) return;
        if (consent.analytics) loadAnalytics();
        else if (window.gtag) window.gtag('consent', 'update', { analytics_storage: 'denied', ad_storage: 'denied', ad_user_data: 'denied', ad_personalization: 'denied' });
        if (consent.marketing) loadMarketing();
        else if (window.fbq) window.fbq('consent', 'revoke');
        window.setTimeout(sendPendingConversions, 0);
    }

    function openPreferences() {
        panel.hidden = false;
        preferences.classList.remove('hidden');
        actions.classList.add('hidden');
        analyticsInput.checked = !!(consent && consent.analytics);
        marketingInput.checked = !!(consent && consent.marketing);
    }

    document.getElementById('aec-cookie-accept').addEventListener('click', function () {
        saveConsent({ analytics: true, marketing: true });
    });
    document.getElementById('aec-cookie-reject').addEventListener('click', function () {
        saveConsent({ analytics: false, marketing: false });
    });
    document.getElementById('aec-cookie-customize').addEventListener('click', openPreferences);
    document.getElementById('aec-cookie-save').addEventListener('click', function () {
        saveConsent({ analytics: analyticsInput.checked, marketing: marketingInput.checked });
    });
    document.getElementById('aec-cookie-close').addEventListener('click', function () {
        panel.hidden = true;
    });
    window.aecOpenCookieSettings = openPreferences;
    window.addEventListener('aec:purchase-ready', sendPendingConversions);

    consent = readConsent();
    if (consent) {
        applyConsent();
    } else {
        panel.hidden = false;
    }
})();
</script>
