# SEO, Pixel and conversion tracking — production checklist

This checklist documents the browser tracking implementation and the external steps required to activate it. Saving IDs in Admin is not proof that provider-side events are being received.

## Implemented in the application

Admin → SEO & Marketing settings provide:
- GA4 Measurement ID (G-...)
- Meta Pixel ID (digits only)
- Optional Google Tag Manager container ID (GTM-...)

The public cookie panel gates optional scripts. No optional tracker is loaded until a visitor makes a choice. Analytics consent enables native GA4 (when a native GA4 ID is configured) and the analytics consent state for GTM. Marketing consent enables Meta Pixel (when a Pixel ID is configured) and advertising consent for GTM. Reject keeps optional consent denied. Consent preferences are stored in local storage for up to 180 days and can be reopened using the site's Cookie settings control.

Current browser events:
| Business event | GA4 event | Meta event | Condition |
|---|---|---|---|
| Public page view | page_view | PageView | Relevant consent granted |
| Lead | generate_lead | Lead | Successful lead flow exposes an event ID and relevant consent is granted |
| Fully paid order | purchase | Purchase | Order success page exposes a purchase event only when status is paid and verified amount covers total |

Purchase payload includes order number as transaction_id, order total as value, and BDT currency. The checkout success page suppresses the ordinary page-view event to avoid counting the confirmation page as another content page. Browser local-storage keys reduce repeated event sends on refresh in the same browser.

Known limitations (do not claim these are implemented):
- No Meta Conversions API/server-side event delivery.
- No server/browser event deduplication contract or guaranteed attribution.
- No automatic AddToCart, InitiateCheckout or ViewContent events documented here.
- No guarantee against ad blockers, browser privacy controls, denied consent or network failures.
- A browser event attempt does not guarantee provider attribution or reporting.

## Configure provider accounts

### GA4
1. Create/select the production GA4 web data stream for https://alomsenglishcenter.com.
2. Copy the Measurement ID beginning G-.
3. Enter it in Admin → SEO & Marketing → GA4 Measurement ID.
4. For the reporting dashboard, separately configure Google OAuth and the numeric GA4 Property ID. The browser Measurement ID does not authorize Analytics Data API reporting.

### Meta Pixel
1. Create/select the correct business-owned Pixel/Dataset in Meta Events Manager.
2. Verify alomsenglishcenter.com in the business account and review applicable web-event configuration.
3. Enter the Pixel ID (digits only) in Admin → SEO & Marketing → Meta Pixel ID.
4. Do not put an access token or secret in the Pixel ID field. This browser integration does not use a Conversions API token.

### Optional Google Tag Manager
1. Create a web container for the production domain and publish a reviewed container version.
2. Enter its GTM-... ID in Admin → SEO & Marketing.
3. Configure every tag to require the correct consent category: analytics tags require Analytics; advertising/remarketing tags require Marketing.
4. Avoid duplicate GA4 or Meta tags. If deploying GA4 or Meta through GTM rather than the native site fields, leave the matching native ID blank.
5. Use GTM Preview/Tag Assistant before publishing.

## Staging acceptance tests — required

Use a separate staging domain and test properties/pixels where available. Do not generate fake production revenue.

- [ ] Clear local storage and cookies in a clean browser profile. Before a choice, confirm no optional Google or Meta tracker request is sent.
- [ ] Choose Reject optional. Navigate public pages; verify optional trackers remain inactive.
- [ ] Choose Analytics only. Confirm GA4 page_view is received and Meta Pixel is not activated.
- [ ] Choose Marketing only. Confirm Meta PageView is received and GA4 analytics remains denied.
- [ ] Choose Accept all. Confirm each expected tracker loads once and there are no duplicate PageView events.
- [ ] Submit one staging lead form and one marketing popup lead. Confirm one generate_lead and one Lead event per successful lead, and verify the lead appears in the Admin lead inbox.
- [ ] Create an unpaid and a partially paid staging order. Confirm neither sends Purchase.
- [ ] Fully verify a staging order through the normal payment-verification workflow. Confirm one GA4 purchase and one Meta Purchase with matching transaction ID/value and BDT currency.
- [ ] Refresh/revisit the success page. Confirm the browser duplicate guard prevents another event in the same browser profile.
- [ ] Withdraw consent and verify no further optional events are emitted.
- [ ] Test cookie settings on desktop and mobile; inspect console/network for CSP errors, blocked scripts and duplicate tags.
- [ ] Confirm authenticated/admin pages do not unexpectedly load public marketing tags.

Provider verification:
- GA4: DebugView and Realtime; inspect page_view, generate_lead and purchase parameters.
- Meta: Events Manager → Test Events and Meta Pixel Helper; inspect PageView, Lead and Purchase values.
- GTM: Preview/Tag Assistant; confirm tag firing respects consent and that only one tag instance emits each event.

## Production activation

- [ ] Back up database and current release; deploy through the normal reviewed release process.
- [ ] Confirm HTTPS, canonical domain and published privacy/cookie policy.
- [ ] Obtain the correct production GA4 Measurement ID, Meta Pixel ID and optional GTM ID from the account owner.
- [ ] Save IDs only in Admin settings; never commit credentials or production .env values.
- [ ] Clear/rebuild Laravel config and view caches if deployment/config changes require it.
- [ ] Repeat reject/analytics-only/marketing-only tests on the live domain before enabling campaigns.
- [ ] Verify events in provider dashboards. Record property/Pixel IDs, test date, test order reference and reviewer in the private release record.
- [ ] Have the business/privacy owner approve consent language, retention and remarketing configuration.

## Rollback

If unexpected or duplicate events occur, clear the affected ID in Admin → SEO & Marketing and/or pause the GTM container. Clear application caches as appropriate, then confirm the public page no longer loads the unwanted tracker. Preserve the previous IDs and GTM container version in the organization's private credential vault, not in Git.

## Sign-off record

| Environment | GA4 | Meta Pixel | GTM | Consent | Lead | Purchase | Reviewer/date |
|---|---|---|---|---|---|---|---|
| Staging | Pending | Pending | Optional | Pending | Pending | Pending | |
| Production | Pending | Pending | Optional | Pending | Pending | Pending | |

A row is complete only after the corresponding provider-side tests pass.