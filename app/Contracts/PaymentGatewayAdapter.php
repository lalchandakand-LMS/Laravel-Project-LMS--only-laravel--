<?php

namespace App\Contracts;

use App\Models\Order;
use Illuminate\Http\Request;

interface PaymentGatewayAdapter
{
    /**
     * Create a provider-side payment session and return normalized redirect/reference data.
     * Implementations must never mark an order paid at this stage.
     */
    public function initiate(Order $order, array $context = []): array;

    /**
     * Verify provider callback authenticity and return a normalized transaction result.
     * Must validate signatures, amount, currency, order reference and provider transaction ID.
     */
    public function verifyCallback(Request $request): array;

    /** Query the provider for a transaction when callback delivery is delayed. */
    public function queryTransaction(string $providerTransactionId): array;
}
