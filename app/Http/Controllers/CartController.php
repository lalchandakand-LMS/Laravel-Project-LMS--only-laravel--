<?php

namespace App\Http\Controllers;

use App\Models\Coupon;
use App\Models\Course;
use App\Models\Ebook;
use App\Models\Product;
use App\Services\ShippingFeeCalculator;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class CartController extends Controller
{
    /** @return array<int, array{key:string,type:string,id:int,title:string,price:float,image:string,url:string}> */
    public static function items(): array
    {
        $rows = [];
        foreach ((array) session('cart', []) as $key) {
            [$type, $id] = array_pad(explode(':', $key), 2, null);
            $id = (int) $id;

            if ($type === 'course' && ($c = Course::published()->find($id)) && ! auth()->user()->hasCourse($c)) {
                $rows[] = [
                    'key' => $key, 'type' => 'course', 'id' => $id, 'title' => $c->title,
                    'price' => $c->final_price, 'original_price' => (float) $c->price,
                    'has_discount' => $c->has_discount, 'flash_sale_name' => $c->activeFlashSale()?->name,
                    'image' => $c->thumbnail_url, 'url' => route('courses.show', $c->slug),
                ];
            }

            if ($type === 'ebook' && ($e = Ebook::where('is_published', true)->find($id)) && ! $e->ownedBy(auth()->user())) {
                $rows[] = [
                    'key' => $key, 'type' => 'ebook', 'id' => $id, 'title' => $e->title,
                    'price' => $e->final_price, 'original_price' => (float) $e->price,
                    'has_discount' => $e->has_discount, 'flash_sale_name' => $e->activeFlashSale()?->name,
                    'image' => $e->coverUrl(), 'url' => route('ebooks.show', $e->slug),
                ];
            }

            if ($type === 'product' && ($p = Product::published()->find($id))) {
                $rows[] = [
                    'key' => $key, 'type' => 'product', 'id' => $id, 'title' => $p->title,
                    'price' => (float) $p->price, 'original_price' => (float) $p->price,
                    'has_discount' => false, 'flash_sale_name' => null,
                    'image' => $p->imageUrl(), 'url' => route('products.show', $p->slug),
                    'qty' => max(1, (int) data_get(session('cart_quantities', []), $key, 1)),
                ];
            }
        }
        return $rows;
    }

    public static function totals(?int $branchId = null, ?Coupon $lockedCoupon = null): array
    {
        $items = self::items();
        $subtotal = round(array_sum(array_map(fn (array $item) => (float) $item['price'] * (int) ($item['qty'] ?? 1), $items)), 2);
        $coupon = null;
        $discount = 0.0;
        $eligibleSubtotal = 0.0;

        if ($code = session('coupon')) {
            $candidate = $lockedCoupon ?? Coupon::where('code', $code)->first();
            if ($candidate) {
                $eligibleSubtotal = $candidate->eligibleSubtotal($items);
                $effectiveBranchId = $branchId ?? $candidate->branch_id;
                if ($candidate->isValidFor($eligibleSubtotal, auth()->user(), $items, $effectiveBranchId)) {
                    $coupon = $candidate;
                    $discount = $coupon->discountFor($eligibleSubtotal);
                }
            }
        }

        $shippingFee = ShippingFeeCalculator::calculate($items, $subtotal, $discount);

        return [
            'items' => $items,
            'subtotal' => $subtotal,
            'eligible_subtotal' => $eligibleSubtotal,
            'discount' => $discount,
            'shipping_fee' => $shippingFee,
            'total' => max(0, round($subtotal - $discount + $shippingFee, 2)),
            'coupon' => $coupon,
            'count' => count($items),
        ];
    }

    public function index()
    {
        return view('cart.index', self::totals());
    }

    public function add(Request $request, string $type, int $id)
    {
        abort_unless(in_array($type, ['course', 'ebook', 'product'], true), 404);

        if ($type === 'course') {
            $course = Course::published()->findOrFail($id);
            if ($request->user()->hasCourse($course)) {
                return back()->with('info', 'You already own this course.');
            }
            if ($course->final_price <= 0) {
                return redirect()->route('courses.enroll-free', $course->slug);
            }
        } elseif ($type === 'ebook') {
            $ebook = Ebook::where('is_published', true)->findOrFail($id);
            if ($ebook->ownedBy($request->user())) {
                return back()->with('info', 'You already own this e-book.');
            }
        } else {
            Product::published()->findOrFail($id);
        }

        $key = "{$type}:{$id}";
        $cart = (array) session('cart', []);
        if (! in_array($key, $cart, true)) {
            $cart[] = $key;
        }
        session(['cart' => $cart]);

        return redirect()->route('cart.index')->with('success', 'Added to cart.');
    }

    public function updateQuantity(Request $request, string $key)
    {
        abort_unless(preg_match('/^product:\\d+$/', $key) === 1, 404);
        $data = $request->validate(['quantity' => 'required|integer|min:1|max:20']);
        [, $id] = explode(':', $key, 2);
        $product = Product::published()->findOrFail((int) $id);
        if ($data['quantity'] > $product->availableQuantity()) {
            throw ValidationException::withMessages(['quantity' => 'Requested quantity exceeds available stock.']);
        }
        abort_unless(in_array($key, (array) session('cart', []), true), 404);
        $quantities = (array) session('cart_quantities', []);
        $quantities[$key] = (int) $data['quantity'];
        session(['cart_quantities' => $quantities]);

        return back()->with('success', 'Cart quantity updated.');
    }

    public function remove(string $key)
    {
        session(['cart' => array_values(array_diff((array) session('cart', []), [$key]))]);
        $quantities = (array) session('cart_quantities', []);
        unset($quantities[$key]);
        session(['cart_quantities' => $quantities]);
        return back()->with('success', 'Removed from cart.');
    }

    public function applyCoupon(Request $request)
    {
        $request->validate(['code' => 'required|string']);
        $coupon = Coupon::where('code', strtoupper(trim($request->code)))->first();
        $items = self::items();
        $eligibleSubtotal = $coupon?->eligibleSubtotal($items) ?? 0;

        if (! $coupon || ! $coupon->isValidFor($eligibleSubtotal, $request->user(), $items, $coupon->branch_id)) {
            return back()->withErrors(['code' => 'This coupon is invalid, unavailable for these items, or its usage limit has been reached.']);
        }

        session(['coupon' => $coupon->code]);
        return back()->with('success', 'Coupon applied: '.$coupon->code);
    }

    public function removeCoupon()
    {
        session()->forget('coupon');
        return back()->with('success', 'Coupon removed.');
    }
}
