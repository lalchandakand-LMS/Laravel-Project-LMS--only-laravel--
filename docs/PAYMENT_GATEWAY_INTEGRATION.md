# Payment gateway integration contract

The LMS supports branch-configured manual payment submission and staff verification. The Admin → Payment Methods screen controls global method availability and stores future provider credentials encrypted with Laravel Crypt. Secret values are not rendered back into the form.

For each provider, use an adapter implementing App\\Contracts\\PaymentGatewayAdapter. The bKash Checkout v1.2.0-beta sandbox adapter is implemented; Nagad, Rocket and SSLCommerz still require provider-specific adapters. The Admin screen stores credentials encrypted and exposes a sandbox readiness state. Live mode remains locked:

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

### bKash Checkout v1.2.0-beta — sandbox adapter implemented

The application now contains a sandbox-only `BkashCheckoutAdapter` for the documented Checkout v1.2.0-beta API and a provider callback flow. This is the one-time Checkout URL/API v1.2.0-beta product, not the separate Tokenized Checkout v2 Agreement/binding API. Tokenized Checkout v2 is not enabled by this adapter.

- Uses the bKash Checkout v1.2.0-beta grant-token, create, execute and payment-status endpoints.
- Only the official HTTPS Checkout sandbox host `checkout.sandbox.bka.sh` is allowed. The configured base URL cannot point to arbitrary hosts, preventing an Admin setting from becoming an SSRF vector.
- Merchant credentials are read from the existing encrypted Admin payment settings. Required fields are app key, app secret, API username and API password. Access tokens are cached and API calls use a 30-second timeout.
- The create-payment response only creates a pending local payment session and redirects the customer to the provider-hosted checkout.
- The merchant callback URL must be configured to the application's fixed `payments/bkash/callback` route during bKash merchant onboarding. The browser return is not trusted as proof of payment. The callback runs provider execute and an independent server-to-server payment-status query, then compares provider payment ID, invoice/order number, exact amount, BDT currency and transaction ID under order/payment row locks.
- A separate `POST /payments/bkash/webhook` listener verifies the AWS SNS signature (version 1/SHA-1 or version 2/SHA-256), allowlists the regional SNS signing-certificate host/path, pins the exact merchant Topic ARN, handles subscription confirmation safely, and reconciles each signed notification through bKash Search Transaction API before settlement.
- Provider payment IDs and transaction IDs are unique in the local ledger. Repeated callbacks/webhooks are idempotent. Manual Admin approval is blocked for provider-initiated pending payments.
- The checkout button is exposed only outside the production environment and only when required sandbox credentials are complete and bKash is enabled for the order's branch.

**Not yet certified:** no merchant sandbox credentials or provisioned SNS Topic ARN were supplied in this repository task, so a real bKash sandbox wallet transaction and webhook subscription delivery could not be run. CI uses mocked provider responses and cryptographic signature fixtures. Obtain the merchant's own sandbox credentials and Topic ARN; test create → customer PIN approval → execute/query → duplicate callback → signed webhook → duplicate webhook → failure/timeout reconciliation before any production rollout.

### Not implemented / Live remains disabled

- **Nagad:** obtain the current official merchant integration package, API endpoints, credentials and callback/signature rules. No guessed cryptographic behavior is accepted.
- **Rocket:** obtain DBBL's approved merchant integration/API specification and credentials. Public merchant payment information is not enough to implement a server API.
- **SSLCommerz:** the static website badge is not a gateway integration. No adapter or route is enabled.

Official starting points:
- bKash Developer Portal: https://developer.bka.sh/
- bKash official Checkout v1.2.0-beta sandbox demo: https://merchantdemo.sandbox.bka.sh/checkout/version/v1.2.0-beta
- bKash official API reference (grant token, create, execute, query, search): https://developer.bka.sh/v1.2.0-beta/reference
- bKash official merchant webhook listener sample: https://github.com/bKash-developer/merchant-webhook-endpoint

### Live-mode acceptance gate for each provider

1. Official merchant contract and current provider API specification received.
2. Merchant sandbox credentials stored encrypted and never returned to the browser.
3. Create/execute/query flow tested against the provider sandbox.
4. Callback/webhook authenticity, replay and idempotency tests pass.
5. Order reference, BDT currency and exact amount match under database row locks.
6. Provider timeout, duplicate callback, late callback, failure, partial payment and refund/reconciliation paths tested.
7. Explicit Admin activation after review; no implicit Live activation from a settings dropdown.

Until all gates pass, manual payment submission and staff verification remain available. bKash automated checkout is sandbox-only and hidden in production. Nagad, Rocket and SSLCommerz remain manual/not integrated.
