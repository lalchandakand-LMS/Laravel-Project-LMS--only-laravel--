@php
    $gaMeasurementId = \App\Models\Setting::get('ga_measurement_id');
    $metaPixelId = \App\Models\Setting::get('meta_pixel_id');
    $gtmContainerId = \App\Models\Setting::get('gtm_container_id');
@endphp
<div id="aec-cookie-consent"
     class="fixed inset-x-3 bottom-3 z-[10000] mx-auto max-w-3xl rounded-2xl border border-slate-200 bg-white p-5 shadow-2xl"
     role="dialog" aria-modal="false" aria-labelledby="aec-cookie-title"
     data-ga-id="{{ $gaMeasurementId }}"
     data-meta-pixel-id="{{ $metaPixelId }}"
     data-gtm-id="{{ $gtmContainerId }}"
     data-disable-pageview="{{ request()->routeIs('checkout.success') ? '1' : '0' }}"
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
        <label class="flex items-center justify-between gap-3 border-t border-slate-200 py-2 text-sm"><span><b>Analytics</b><small class="block text-xs text-slate-500">Google Analytics 4 or analytics tags configured through Google Tag Manager.</small></span><input id="aec-consent-analytics" type="checkbox" class="rounded border-slate-300 text-orange-600"></label>
        <label class="flex items-center justify-between gap-3 border-t border-slate-200 py-2 text-sm"><span><b>Marketing</b><small class="block text-xs text-slate-500">Meta Pixel or marketing tags configured through Google Tag Manager.</small></span><input id="aec-consent-marketing" type="checkbox" class="rounded border-slate-300 text-orange-600"></label>
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
    var gtmId = panel.dataset.gtmId || '';
    if (!gaId && !pixelId && !gtmId) return;

    var storageKey = 'aec_cookie_consent_v1';
    var preferences = document.getElementById('aec-cookie-preferences');
    var actions = document.getElementById('aec-cookie-actions');
    var analyticsInput = document.getElementById('aec-consent-analytics');
    var marketingInput = document.getElementById('aec-consent-marketing');
    var consent = null;

    function readConsent() {
        try {
            var value = JSON.parse(window.localStorage.getItem(storageKey) || 'null');
            if (!value || value.version !== 1 || !value.savedAt) return null;
            var savedAt = new Date(value.savedAt).getTime();
            if (!Number.isFinite(savedAt) || Date.now() - savedAt > 180 * 24 * 60 * 60 * 1000) {
                window.localStorage.removeItem(storageKey);
                return null;
            }
            return { analytics: value.analytics === true, marketing: value.marketing === true };
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
        if (!gaId || gtmId || window.__aecGaLoaded) return;
        window.__aecGaLoaded = true;
        window.dataLayer = window.dataLayer || [];
        window.gtag = window.gtag || function () { window.dataLayer.push(arguments); };
        window.gtag('consent', 'default', { analytics_storage: 'denied', ad_storage: 'denied', ad_user_data: 'denied', ad_personalization: 'denied' });
        window.gtag('consent', 'update', { analytics_storage: 'granted' });
        window.gtag('js', new Date());
        window.gtag('config', gaId, { anonymize_ip: true, send_page_view: panel.dataset.disablePageview !== '1' });
        var script = document.createElement('script');
        script.async = true;
        script.src = 'https://www.googletagmanager.com/gtag/js?id=' + encodeURIComponent(gaId);
        document.head.appendChild(script);
    }

    function loadTagManager() {
        if (!gtmId || window.__aecGtmLoaded) return;
        window.__aecGtmLoaded = true;
        window.dataLayer = window.dataLayer || [];
        window.gtag = window.gtag || function () { window.dataLayer.push(arguments); };
        window.gtag('consent', 'default', {
            analytics_storage: 'denied',
            ad_storage: 'denied',
            ad_user_data: 'denied',
            ad_personalization: 'denied'
        });
        window.gtag('consent', 'update', {
            analytics_storage: consent && consent.analytics ? 'granted' : 'denied',
            ad_storage: consent && consent.marketing ? 'granted' : 'denied',
            ad_user_data: consent && consent.marketing ? 'granted' : 'denied',
            ad_personalization: consent && consent.marketing ? 'granted' : 'denied'
        });
        window.dataLayer.push({ 'gtm.start': new Date().getTime(), event: 'gtm.js' });
        var script = document.createElement('script');
        script.async = true;
        script.src = 'https://www.googletagmanager.com/gtm.js?id=' + encodeURIComponent(gtmId);
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
        if (panel.dataset.disablePageview !== '1') window.fbq('track', 'PageView');
    }

    function sendPendingConversions() {
        var purchase = window.aecPurchaseEvent;
        if (purchase) {
            var transactionId = String(purchase.transaction_id || '');
            if (transactionId) {
                if (consent && consent.analytics && window.gtag && !window.__aecGaPurchaseSent) {
                    var gaKey = 'aec_ga_purchase_' + transactionId;
                    var gaAlreadySent = false;
                    try { gaAlreadySent = !!window.localStorage.getItem(gaKey); } catch (error) {}
                    if (!gaAlreadySent) {
                        window.gtag('event', 'purchase', purchase);
                        window.__aecGaPurchaseSent = true;
                        try { window.localStorage.setItem(gaKey, '1'); } catch (error) {}
                    }
                }
                if (consent && consent.marketing && window.fbq && !window.__aecMetaPurchaseSent) {
                    var metaKey = 'aec_meta_purchase_' + transactionId;
                    var metaAlreadySent = false;
                    try { metaAlreadySent = !!window.localStorage.getItem(metaKey); } catch (error) {}
                    if (!metaAlreadySent) {
                        window.fbq('track', 'Purchase', { value: purchase.value, currency: purchase.currency });
                        window.__aecMetaPurchaseSent = true;
                        try { window.localStorage.setItem(metaKey, '1'); } catch (error) {}
                    }
                }
            }
        }

        var lead = window.aecLeadEvent;
        var eventId = lead && String(lead.event_id || '');
        if (!eventId) return;

        if (consent && consent.analytics && window.gtag && !window.__aecGaLeadSent) {
            var gaLeadKey = 'aec_ga_lead_' + eventId;
            var gaLeadAlreadySent = false;
            try { gaLeadAlreadySent = !!window.localStorage.getItem(gaLeadKey); } catch (error) {}
            if (!gaLeadAlreadySent) {
                window.gtag('event', 'generate_lead', { method: String(lead.method || 'website'), event_id: eventId });
                window.__aecGaLeadSent = true;
                try { window.localStorage.setItem(gaLeadKey, '1'); } catch (error) {}
            }
        }
        if (consent && consent.marketing && window.fbq && !window.__aecMetaLeadSent) {
            var metaLeadKey = 'aec_meta_lead_' + eventId;
            var metaLeadAlreadySent = false;
            try { metaLeadAlreadySent = !!window.localStorage.getItem(metaLeadKey); } catch (error) {}
            if (!metaLeadAlreadySent) {
                window.fbq('track', 'Lead', { content_name: String(lead.method || 'website') }, { eventID: eventId });
                window.__aecMetaLeadSent = true;
                try { window.localStorage.setItem(metaLeadKey, '1'); } catch (error) {}
            }
        }
    }

    function applyConsent() {
        if (!consent) return;
        if (gtmId && (consent.analytics || consent.marketing)) loadTagManager();
        if (consent.analytics) {
            loadAnalytics();
            if (window.gtag) window.gtag('consent', 'update', { analytics_storage: 'granted' });
        } else if (window.gtag) {
            window.gtag('consent', 'update', { analytics_storage: 'denied', ad_storage: 'denied', ad_user_data: 'denied', ad_personalization: 'denied' });
        }
        if (gtmId && window.gtag) {
            window.gtag('consent', 'update', {
                analytics_storage: consent.analytics ? 'granted' : 'denied',
                ad_storage: consent.marketing ? 'granted' : 'denied',
                ad_user_data: consent.marketing ? 'granted' : 'denied',
                ad_personalization: consent.marketing ? 'granted' : 'denied'
            });
        }
        if (consent.marketing) {
            loadMarketing();
            if (window.fbq && window.__aecMetaLoaded) window.fbq('consent', 'grant');
        } else if (window.fbq) {
            window.fbq('consent', 'revoke');
        }
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
    window.addEventListener('aec:lead-ready', sendPendingConversions);

    consent = readConsent();
    if (consent) {
        applyConsent();
    } else {
        panel.hidden = false;
    }
})();
</script>
