<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Payment;
use App\Services\OrderService;
use App\Services\Payments\BkashCheckoutAdapter;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

class BkashPaymentController extends Controller
{
    public function initiate(Request $request, Order $order, BkashCheckoutAdapter $adapter)
    {
        abort_unless($order->user_id === $request->user()->id, 403);

        try {
            $session = DB::transaction(function () use ($request, $order, $adapter) {
                // Serialize checkout creation per order. The pending row must be
                // checked and inserted while holding the same order lock, otherwise
                // two parallel requests can create two live provider sessions.
                $lockedOrder = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
                abort_unless($lockedOrder->user_id === $request->user()->id, 403);
                $lockedOrder->load('branch');

                abort_unless($lockedOrder->branch && $lockedOrder->branch->is_active, 403);

                if (! in_array('bkash', $lockedOrder->branch->enabledStudentPaymentMethods(), true)) {
                    throw ValidationException::withMessages(['gateway' => 'bKash is not enabled for this order branch.']);
                }

                if (! in_array($lockedOrder->status, ['pending', 'partially_paid'], true) || $lockedOrder->dueAmount() <= 0) {
                    throw ValidationException::withMessages(['payment' => 'This order is not awaiting payment.']);
                }

                if ($lockedOrder->payments()->where('status', 'pending')->exists()) {
                    throw ValidationException::withMessages(['payment' => 'A payment is already awaiting verification for this order.']);
                }

                // Keep the order lock until the provider session is persisted so a
                // concurrent request cannot pass the pending-payment check.
                $session = $adapter->initiate($lockedOrder);

                Payment::create([
                    'order_id' => $lockedOrder->id,
                    'gateway' => 'bkash',
                    'provider_payment_id' => $session['payment_id'],
                    'amount' => $session['amount'],
                    'status' => 'pending',
                    'payload' => [
                        'provider' => 'bkash',
                        'invoice' => $session['invoice'],
                        'currency' => $session['currency'],
                        'initiated_at' => now()->toIso8601String(),
                    ],
                ]);

                return $session;
            });
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            report($exception);
            throw ValidationException::withMessages([
                'gateway' => 'The bKash sandbox checkout could not be started. Check the Admin API configuration and try again.',
            ]);
        }

        return redirect()->away($session['redirect_url']);
    }

    /** Browser return. Browser-supplied status is ignored; bKash is queried server-to-server. */
    public function callback(Request $request, BkashCheckoutAdapter $adapter)
    {
        $paymentId = trim((string) ($request->input('paymentID') ?: $request->query('paymentID')));
        $payment = Payment::where('gateway', 'bkash')
            ->where('provider_payment_id', $paymentId)
            ->firstOrFail();
        $order = Order::whereKey($payment->order_id)->firstOrFail();

        if ($payment->status === 'verified') {
            return redirect()->route('checkout.success', $order->order_no)
                ->with('success', 'Your bKash payment was already confirmed.');
        }

        try {
            $result = $adapter->verifyCallback($request);
        } catch (Throwable $exception) {
            report($exception);

            return redirect()->route('checkout.success', $order->order_no)
                ->with('warning', 'bKash has not returned a confirmed result yet. Do not pay again; the payment remains pending reconciliation.');
        }

        if (($result['status'] ?? null) !== 'success') {
            if (($result['status'] ?? null) === 'failed') {
                $rejected = DB::transaction(function () use ($payment, $order, $result) {
                    // Use the same order-then-payment lock order as webhook settlement.
                    // This prevents a stale browser failure from winning the payment lock
                    // while a successful webhook is holding the order lock.
                    Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
                    $lockedPayment = Payment::whereKey($payment->id)->lockForUpdate()->firstOrFail();

                    if ($lockedPayment->status !== 'pending') {
                        return $lockedPayment->status === 'rejected';
                    }

                    $lockedPayment->update([
                        'status' => 'rejected',
                        'rejection_reason' => $result['reason'] ?? 'bKash reported an unsuccessful transaction.',
                        'payload' => array_merge($lockedPayment->payload ?? [], ['provider_status' => $result['provider_status'] ?? 'Failed']),
                    ]);

                    return true;
                });

                if (! $rejected) {
                    return redirect()->route('checkout.success', $order->order_no)
                        ->with('success', 'Your bKash payment was already confirmed.');
                }

                return redirect()->route('checkout.success', $order->order_no)
                    ->with('error', 'bKash did not complete the payment. You may try again.');
            }

            return redirect()->route('checkout.success', $order->order_no)
                ->with('warning', 'bKash is still processing this payment. Do not submit a second payment.');
        }

        try {
            $settled = $this->settle($order, $payment, $result, true);
        } catch (ValidationException $exception) {
            return redirect()->route('checkout.success', $order->order_no)
                ->withErrors($exception->errors());
        }

        if (! $settled) {
            return redirect()->route('checkout.success', $order->order_no)
                ->withErrors(['payment' => 'The bKash transaction did not match this order. Contact the office before retrying.']);
        }

        return redirect()->route('checkout.success', $order->order_no)
            ->with('success', 'bKash payment was verified directly with the provider.');
    }

    /** Signed bKash/AWS SNS webhook listener. */
    public function webhook(Request $request, BkashCheckoutAdapter $adapter)
    {
        try {
            $event = $adapter->verifyWebhook($request);
        } catch (ValidationException $exception) {
            return response()->json(['received' => false, 'message' => 'Invalid webhook signature or topic.'], 403);
        } catch (Throwable $exception) {
            report($exception);
            return response()->json(['received' => false], 503);
        }

        if (($event['Type'] ?? '') === 'SubscriptionConfirmation') {
            try {
                $adapter->confirmWebhookSubscription($event);
                return response()->noContent();
            } catch (Throwable $exception) {
                report($exception);
                return response()->json(['received' => false], 503);
            }
        }

        if (($event['Type'] ?? '') !== 'Notification') {
            return response()->noContent();
        }

        $notification = json_decode((string) ($event['Message'] ?? ''), true);
        if (! is_array($notification)
            || strcasecmp((string) ($notification['transactionStatus'] ?? ''), 'Completed') !== 0
            || blank($notification['trxID'] ?? null)
            || blank($notification['merchantInvoiceNumber'] ?? null)) {
            return response()->noContent();
        }

        try {
            // A signed notification is still reconciled with bKash Search Transaction API.
            $result = $adapter->searchTransaction((string) $notification['trxID']);
        } catch (Throwable $exception) {
            report($exception);
            return response()->json(['received' => false], 503);
        }

        if (($result['status'] ?? null) === 'pending') {
            // Provider search can lag behind an SNS notification; ask SNS to retry later.
            return response()->json(['received' => false], 503);
        }
        if (($result['status'] ?? null) !== 'success') {
            return response()->noContent();
        }

        $sameEvent = ($result['transaction_id'] ?? '') === (string) $notification['trxID']
            && (blank($result['invoice'] ?? null) || ($result['invoice'] ?? '') === (string) $notification['merchantInvoiceNumber'])
            && strtoupper((string) ($result['currency'] ?? '')) === 'BDT'
            && strtoupper((string) ($notification['currency'] ?? '')) === 'BDT'
            && round((float) ($result['amount'] ?? 0), 2) === round((float) ($notification['amount'] ?? 0), 2);

        if (! $sameEvent) {
            return response()->noContent();
        }
        $result['invoice'] = (string) $notification['merchantInvoiceNumber'];

        $order = Order::where('order_no', $result['invoice'])->first();
        if (! $order) {
            return response()->noContent();
        }

        $payment = Payment::where('order_id', $order->id)
            ->where('gateway', 'bkash')
            ->where('status', 'pending')
            ->whereNotNull('provider_payment_id')
            ->latest('id')
            ->first();

        if (! $payment) {
            // The same SNS event may arrive after the browser callback settled it.
            return response()->noContent();
        }

        try {
            $this->settle($order, $payment, $result, false);
        } catch (ValidationException $exception) {
            report($exception);
            return response()->noContent();
        } catch (Throwable $exception) {
            report($exception);
            return response()->json(['received' => false], 503);
        }

        return response()->noContent();
    }

    private function settle(Order $order, Payment $payment, array $result, bool $requirePaymentId): bool
    {
        return DB::transaction(function () use ($order, $payment, $result, $requirePaymentId) {
            $lockedOrder = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
            $lockedPayment = Payment::whereKey($payment->id)->lockForUpdate()->firstOrFail();

            if ($lockedPayment->status === 'verified') {
                return true;
            }

            if ($lockedPayment->status !== 'pending'
                || ! in_array($lockedOrder->status, ['pending', 'partially_paid'], true)) {
                throw ValidationException::withMessages(['payment' => 'This order is no longer eligible for automatic settlement.']);
            }

            $paymentIdMatches = $requirePaymentId
                ? ($result['payment_id'] ?? '') === $lockedPayment->provider_payment_id
                : (blank($result['payment_id'] ?? null) || $result['payment_id'] === $lockedPayment->provider_payment_id);

            $valid = $paymentIdMatches
                && ($result['invoice'] ?? '') === $lockedOrder->order_no
                && strtoupper((string) ($result['currency'] ?? '')) === 'BDT'
                && round((float) ($result['amount'] ?? 0), 2) === round((float) $lockedPayment->amount, 2)
                && filled($result['transaction_id'] ?? null);

            if (! $valid) {
                $lockedPayment->update([
                    'status' => 'rejected',
                    'rejection_reason' => 'Provider status query did not match the order reference, amount, currency or transaction ID.',
                    'payload' => array_merge($lockedPayment->payload ?? [], ['provider_result' => $result]),
                ]);

                return false;
            }

            if (Payment::where('transaction_id', $result['transaction_id'])
                ->whereKeyNot($lockedPayment->id)->exists()) {
                throw ValidationException::withMessages(['payment' => 'This bKash transaction ID has already been recorded.']);
            }

            $alreadyPaid = $lockedOrder->amountPaid();
            if (round($alreadyPaid + $lockedPayment->amount, 2) > round($lockedOrder->total, 2)) {
                throw ValidationException::withMessages(['payment' => 'The bKash payment would exceed the outstanding order balance.']);
            }

            $lockedPayment->update([
                'transaction_id' => $result['transaction_id'],
                'sender_number' => $result['customer_msisdn'] ?: null,
                'status' => 'verified',
                'verified_at' => now(),
                'payload' => array_merge($lockedPayment->payload ?? [], ['provider_result' => $result]),
            ]);

            if (round($alreadyPaid + $lockedPayment->amount, 2) >= round($lockedOrder->total, 2)) {
                OrderService::fulfill($lockedOrder);
            } else {
                $lockedOrder->update(['status' => 'partially_paid']);
            }

            return true;
        });
    }
}
