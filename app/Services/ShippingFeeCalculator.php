<?php

namespace App\Services;

use App\Models\Setting;

class ShippingFeeCalculator
{
    /**
     * Calculate a flat BDT delivery charge for carts containing physical products.
     * A zero free-shipping threshold disables threshold-based free delivery.
     */
    public static function calculate(array $items, float $subtotal, float $discount = 0): float
    {
        $hasPhysicalProducts = collect($items)->contains(
            fn (array $item): bool => ($item['type'] ?? $item['item_type'] ?? null) === 'product'
        );

        if (! $hasPhysicalProducts) {
            return 0.0;
        }

        $threshold = max(0, (float) Setting::get('physical_free_shipping_threshold', 0));
        $discountedSubtotal = max(0, round($subtotal - $discount, 2));

        if ($threshold > 0 && $discountedSubtotal >= $threshold) {
            return 0.0;
        }

        return round(max(0, (float) Setting::get('physical_shipping_fee', 0)), 2);
    }
}
