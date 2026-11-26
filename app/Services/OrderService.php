<?php

namespace App\Services;

use App\Models\Certificate;
use App\Models\Course;
use App\Models\Ebook;
use App\Models\EbookPurchase;
use App\Models\Enrollment;
use App\Models\Order;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OrderService
{
    /** Give the buyer access to everything in the order. */
    public static function fulfill(Order $order): void
    {
        DB::transaction(function () use ($order) {
            $order = Order::with('items')->whereKey($order->id)->lockForUpdate()->firstOrFail();

            foreach ($order->items as $item) {
                if ($item->item_type === 'course') {
                    $course = Course::whereKey($item->item_id)->lockForUpdate()->first();
                    if (! $course) {
                        continue;
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
            }

            $order->update(['status' => 'paid']);
        });
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
