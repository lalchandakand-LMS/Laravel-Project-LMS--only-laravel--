# Payment gateway integration contract

The LMS supports branch-configured manual payment submission and staff verification. The Admin → Payment Methods screen controls global method availability and stores future provider credentials encrypted with Laravel Crypt. Secret values are not rendered back into the form.

When merchant credentials become available, add a provider adapter implementing App\\Contracts\\PaymentGatewayAdapter. The Admin screen currently stores credentials only; selecting Live mode does not activate payment processing:

- initiate() creates a provider checkout session and returns provider reference / redirect data. It must not fulfill an order.
- verifyCallback() validates the provider signature and normalizes the callback.
- queryTransaction() performs server-to-server reconciliation if a callback is delayed.

A provider transaction may become verified only after server-side verification of:
1. Callback signature / authenticity.
2. Provider transaction reference and idempotency.
3. Exact order reference, currency and amount.
4. Provider-side successful settlement status.

Use encrypted storage for credentials (Crypt::encryptString) or deployment environment secrets. Never log API secrets, authorization headers, signatures, or full callback credentials. Do not trust a browser redirect as proof of payment.

All provider callbacks must be idempotent. Lock the payment and order rows in a database transaction, reject duplicate provider transaction IDs, and append the normalized provider response to the payment audit payload. Live activation requires merchant credentials and provider sandbox tests.


## Provider readiness and Live-mode lock (2026-11)

The application deliberately rejects Live mode for bKash, Nagad and Rocket until a provider-specific adapter is implemented and independently tested. Storing credentials or enabling a method at branch level must never be mistaken for automated payment collection.

Current integration boundaries:

- **bKash:** official Tokenized Checkout / Checkout onboarding and API documentation are provided through the bKash Developer Portal. The merchant must obtain its own sandbox/live app key, app secret, username and password. Checkout creation is not settlement; server-side execute/query verification is required. See [bKash Tokenized Checkout](https://www.bkash.com/en/page/tokenized_checkout) and [bKash Developer Portal](https://developer.bka.sh/).
- **Nagad:** obtain the current merchant integration package, credential requirements and approved callback/signature procedure directly from Nagad. Do not implement guessed endpoint or cryptographic behavior from unofficial copied examples. See [Nagad](https://nagad.com.bd/).
- **Rocket:** DBBL publicly describes Rocket merchant payment, but public merchant information is not a server API specification. Obtain DBBL's approved merchant integration/API specification and credentials before implementing automated checkout or callback. See [DBBL Rocket Merchant Payment](https://www.dutchbanglabank.com/rocket/merchant-payment.html).

Do not expose a generic callback endpoint that accepts unverified provider claims. Each adapter must define its exact signature/authentication mechanism, transaction query/execute sequence, amount and currency mapping, idempotency key, retry policy, timeout handling and reconciliation rules. A browser redirect alone must never mark an order paid.

Live-mode acceptance gate for each provider:

1. Official merchant contract and current provider API specification received.
2. Sandbox credentials stored encrypted and never returned to the browser.
3. Create/execute/query flow tested against provider sandbox.
4. Callback authentication and replay/idempotency tests pass.
5. Order reference, BDT currency and exact amount match under database row locks.
6. Provider timeout, duplicate callback, late callback, failure and refund/reconciliation paths tested.
7. Explicit Admin activation after review; no implicit Live activation from a settings dropdown.

Until all gates pass, manual payment submission and staff verification remain the active payment workflow.
