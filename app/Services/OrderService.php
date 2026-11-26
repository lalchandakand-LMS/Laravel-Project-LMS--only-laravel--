<?php

namespace App\Services;

use App\Models\Certificate;
use App\Models\Course;
use App\Models\Ebook;
use App\Models\EbookPurchase;
use App\Models\Enrollment;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OrderService
{
    /** Give the buyer access to everything in the order. */
    public static function fulfill(Order $order): void
    {
        DB::transaction(function () use ($order) {
            $order = Order::with('items')->whereKey($order->id)->lockForUpdate()->firstOrFail();
            if ($order->status === 'paid') {
                return;
            }

            foreach ($order->items as $item) {
                if ($item->item_type === 'course') {
                    $course = Course::whereKey($item->item_id)->lockForUpdate()->first();
                    if (! $course) {
                        throw ValidationException::withMessages([
                            'course' => 'The purchased course is no longer available. Restore the course before verifying this payment.',
                        ]);
                    }

                    $existing = Enrollment::where('user_id', $order->user_id)
                        ->where('course_id', $course->id)->first();

                    if ($existing && $existing->status === 'active'
                        && (! $existing->expires_at || $existing->expires_at->isFuture())) {
                        continue;
                    }

                    $activeSeats = Enrollment::where('course_id', $course->id)
                        ->where('status', 'active')
                        ->where(fn ($query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now()))
                        ->count();

                    if ($course->seat_limit && $activeSeats >= $course->seat_limit) {
                        throw ValidationException::withMessages([
                            'course' => "Enrollment capacity has been reached for {$course->title}. The payment requires manual review.",
                        ]);
                    }

                    if ($existing) {
                        $existing->update([
                            'order_id' => $order->id,
                            'branch_id' => $order->branch_id,
                            'price_paid' => $item->price,
                            'status' => 'active',
                            'enrolled_at' => now(),
                            'expires_at' => $course->access_duration_days ? now()->addDays($course->access_duration_days) : null,
                        ]);
                    } else {
                        Enrollment::create([
                            'user_id' => $order->user_id,
                            'course_id' => $course->id,
                            'branch_id' => $order->branch_id,
                            'order_id' => $order->id,
                            'price_paid' => $item->price,
                            'status' => 'active',
                            'enrolled_at' => now(),
                            'expires_at' => $course->access_duration_days ? now()->addDays($course->access_duration_days) : null,
                        ]);
                    }
                }

                if ($item->item_type === 'ebook') {
                    EbookPurchase::firstOrCreate(
                        ['user_id' => $order->user_id, 'ebook_id' => $item->item_id],
                        ['order_id' => $order->id]
                    );
                }

                if ($item->item_type === 'product') {
                    $product = Product::withTrashed()->whereKey($item->item_id)->lockForUpdate()->first();
                    if (! $product || $product->stock_quantity < $item->qty) {
                        throw ValidationException::withMessages([
                            'product' => "Insufficient stock for {$item->title}. The payment cannot be approved until stock is reconciled.",
                        ]);
                    }

                    if ($item->stock_reserved) {
                        if ($product->reserved_quantity < $item->qty) {
                            throw ValidationException::withMessages([
                                'product' => "The reserved stock for {$item->title} is inconsistent. Reconcile inventory before approving payment.",
                            ]);
                        }
                        $product->decrement('reserved_quantity', $item->qty);
                    } elseif ($product->availableQuantity() < $item->qty) {
                        throw ValidationException::withMessages([
                            'product' => "Insufficient unreserved stock for {$item->title}. The payment cannot be approved until stock is reconciled.",
                        ]);
                    }

                    $product->decrement('stock_quantity', $item->qty);
                    if ($item->stock_reserved) {
                        $item->update(['stock_reserved' => false]);
                    }
                }
            }

            $order->update([
                'status' => 'paid',
                'shipping_status' => $order->items->contains('item_type', 'product') ? ($order->shipping_status ?: 'processing') : $order->shipping_status,
            ]);
        });
    }

    /** Reserve every physical line before the order is offered for payment. */
    public static function reserveProducts(Order $order): void
    {
        DB::transaction(function () use ($order) {
            $lockedOrder = Order::with('items')->whereKey($order->id)->lockForUpdate()->firstOrFail();
            if (in_array($lockedOrder->status, ['paid', 'refunded'], true)) {
                return;
            }

            foreach ($lockedOrder->items->where('item_type', 'product') as $item) {
                if ($item->stock_reserved) {
                    continue;
                }

                $product = Product::whereKey($item->item_id)
                    ->where('is_published', true)
                    ->lockForUpdate()
                    ->first();

                if (! $product || $product->availableQuantity() < $item->qty) {
                    throw ValidationException::withMessages([
                        'product' => "There is not enough available stock for {$item->title}. Reduce the quantity or restock the product.",
                    ]);
                }

                $product->increment('reserved_quantity', $item->qty);
                $item->update(['stock_reserved' => true]);
            }
        });
    }

    /** Release stock held by an unpaid or cancelled order. Safe to call more than once. */
    public static function releaseReservations(Order $order): void
    {
        DB::transaction(function () use ($order) {
            $lockedOrder = Order::with('items')->whereKey($order->id)->lockForUpdate()->firstOrFail();
            self::releaseReservationsLocked($lockedOrder);
        });
    }

    private static function releaseReservationsLocked(Order $order): void
    {
        foreach ($order->items->where('item_type', 'product')->where('stock_reserved', true) as $item) {
            $product = Product::withTrashed()->whereKey($item->item_id)->lockForUpdate()->first();
            if ($product) {
                $release = min((int) $item->qty, (int) $product->reserved_quantity);
                if ($release > 0) {
                    $product->decrement('reserved_quantity', $release);
                }
            }

            $item->update(['stock_reserved' => false]);
        }
    }

    /** Cancel stale unpaid orders and release their inventory holds. */
    public static function expireUnpaidOrders(int $hours = 24): int
    {
        $cutoff = now()->subHours(max(1, $hours));
        $ids = Order::where('status', 'pending')
            ->where('created_at', '<=', $cutoff)
            ->whereDoesntHave('payments', fn ($query) => $query->whereIn('status', ['pending', 'verified']))
            ->orderBy('id')
            ->pluck('id');

        $expired = 0;
        foreach ($ids as $id) {
            DB::transaction(function () use ($id, $cutoff, &$expired) {
                $order = Order::with('items')->whereKey($id)->lockForUpdate()->first();
                if (! $order || $order->status !== 'pending' || $order->created_at->gt($cutoff)
                    || $order->amountPaid() > 0
                    || $order->payments()->whereIn('status', ['pending', 'verified'])->exists()) {
                    return;
                }

                self::releaseReservationsLocked($order);
                $order->update(['status' => 'cancelled']);
                $expired++;
            });
        }

        return $expired;
    }

    /** Issue a certificate when the student finished 100% of a course. */
    public static function maybeIssueCertificate(int $userId, Course $course): ?Certificate
    {
        if (! $course->has_certificate) {
            return null;
        }

        $progress = $course->progressFor(\App\Models\User::find($userId));
        if ($progress < 100) {
            return null;
        }

        Enrollment::where('user_id', $userId)->where('course_id', $course->id)
            ->update(['completed_at' => now()]);

        return Certificate::firstOrCreate(
            ['user_id' => $userId, 'course_id' => $course->id],
            ['code' => 'CERT-'.strtoupper(\Illuminate\Support\Str::random(10)), 'issued_at' => now()]
        );
    }
}
