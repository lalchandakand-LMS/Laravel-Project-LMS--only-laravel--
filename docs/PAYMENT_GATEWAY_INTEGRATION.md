# Payment gateway integration contract

The LMS currently supports branch-configured manual payment submission and staff verification. No live provider is enabled by this layer.

When merchant credentials become available, add a provider adapter implementing App\\Contracts\\PaymentGatewayAdapter:

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
