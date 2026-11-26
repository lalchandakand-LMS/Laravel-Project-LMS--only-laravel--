<?php

namespace Tests\Feature;

use App\Http\Controllers\CartController;
use App\Models\Branch;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\User;
use App\Services\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PhysicalProductStoreTest extends TestCase
{
    use RefreshDatabase;

    public function test_published_in_stock_products_are_visible_and_out_of_stock_products_are_hidden(): void
    {
        Product::create([
            'title' => 'English Grammar Book',
            'price' => 450,
            'stock_quantity' => 8,
            'is_published' => true,
        ]);
        Product::create([
            'title' => 'Sold Out Workbook',
            'price' => 250,
            'stock_quantity' => 0,
            'is_published' => true,
        ]);
        Product::create([
            'title' => 'Draft Workbook',
            'price' => 300,
            'stock_quantity' => 5,
            'is_published' => false,
        ]);

        $this->get(route('products.index'))
            ->assertOk()
            ->assertSee('English Grammar Book')
            ->assertDontSee('Sold Out Workbook')
            ->assertDontSee('Draft Workbook');
    }

    public function test_physical_product_can_be_added_to_cart_and_requires_shipping_address_at_checkout(): void
    {
        $student = User::factory()->create(['role' => 'student', 'is_active' => true]);
        $product = Product::create([
            'title' => 'English Grammar Book',
            'price' => 450,
            'stock_quantity' => 8,
            'is_published' => true,
        ]);

        $this->actingAs($student)
            ->post(route('cart.add', ['type' => 'product', 'id' => $product->id]))
            ->assertRedirect(route('cart.index'));

        $this->get(route('cart.index'))->assertOk()->assertSee('English Grammar Book');

        $totals = \App\Http\Controllers\CartController::totals();
        $this->assertSame(1, $totals['count']);
        $this->assertSame('product', $totals['items'][0]['type']);
        $this->assertSame(450.0, $totals['items'][0]['price']);

        $this->post(route('cart.quantity', 'product:'.$product->id), ['quantity' => 3])
            ->assertRedirect();
        $updatedTotals = CartController::totals();
        $this->assertSame(3, $updatedTotals['items'][0]['qty']);
        $this->assertSame(1350.0, $updatedTotals['subtotal']);

        $this->from(route('cart.index'))
            ->post(route('cart.quantity', 'product:'.$product->id), ['quantity' => 9])
            ->assertSessionHasErrors('quantity');
    }

    public function test_paid_fulfillment_decrements_inventory_once_and_is_idempotent(): void
    {
        $student = User::factory()->create(['role' => 'student', 'is_active' => true]);
        $product = Product::create([
            'title' => 'English Grammar Book',
            'price' => 450,
            'stock_quantity' => 8,
            'is_published' => true,
        ]);
        $order = Order::create([
            'user_id' => $student->id,
            'subtotal' => 900,
            'discount' => 0,
            'total' => 900,
            'status' => 'pending',
            'name' => $student->name,
            'phone' => '01712345678',
            'email' => $student->email,
            'address' => 'Dhaka, Bangladesh',
        ]);
        $order->items()->create([
            'item_type' => 'product',
            'item_id' => $product->id,
            'title' => $product->title,
            'price' => 450,
            'qty' => 2,
        ]);

        OrderService::fulfill($order);
        OrderService::fulfill($order);

        $this->assertSame(6, $product->fresh()->stock_quantity);
        $this->assertSame('paid', $order->fresh()->status);
    }

    public function test_admin_shipping_workflow_requires_tracking_and_prevents_backward_transitions(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $student = User::factory()->create(['role' => 'student', 'is_active' => true]);
        $product = Product::create([
            'title' => 'Shipping Test Book',
            'price' => 200,
            'stock_quantity' => 4,
            'is_published' => true,
        ]);
        $order = Order::create([
            'user_id' => $student->id,
            'subtotal' => 200,
            'discount' => 0,
            'total' => 200,
            'status' => 'paid',
            'shipping_status' => 'processing',
            'name' => $student->name,
            'phone' => '01712345678',
            'email' => $student->email,
            'address' => 'Dhaka, Bangladesh',
        ]);
        $order->items()->create([
            'item_type' => 'product',
            'item_id' => $product->id,
            'title' => $product->title,
            'price' => 200,
            'qty' => 1,
        ]);

        $this->actingAs($admin)
            ->post(route('admin.orders.shipping', $order->order_no), ['shipping_status' => 'packed'])
            ->assertSessionHasNoErrors();

        $this->post(route('admin.orders.shipping', $order->order_no), ['shipping_status' => 'shipped'])
            ->assertSessionHasErrors('tracking_number');

        $this->post(route('admin.orders.shipping', $order->order_no), [
            'shipping_status' => 'shipped',
            'shipping_carrier' => 'Courier',
            'tracking_number' => 'TRACK-1001',
        ])->assertSessionHasNoErrors();
        $this->post(route('admin.orders.shipping', $order->order_no), ['shipping_status' => 'delivered'])
            ->assertSessionHasNoErrors();

        $this->post(route('admin.orders.shipping', $order->order_no), ['shipping_status' => 'packed'])
            ->assertSessionHasErrors('shipping_status');

        $this->assertSame('delivered', $order->fresh()->shipping_status);
        $this->assertSame('TRACK-1001', $order->fresh()->tracking_number);
        $this->assertNotNull($order->fresh()->delivered_at);
    }

    public function test_student_checkout_reserves_physical_inventory_before_payment(): void
    {
        $student = User::factory()->create(['role' => 'student', 'is_active' => true]);
        $branch = Branch::create([
            'name' => 'Reservation Branch',
            'code' => 'RSV-01',
            'is_active' => true,
            'payment_methods' => ['bkash'],
        ]);
        $product = Product::create([
            'title' => 'Checkout Reservation Book',
            'price' => 400,
            'stock_quantity' => 5,
            'is_published' => true,
        ]);

        $this->actingAs($student)->withSession(['cart' => ['product:'.$product->id]])
            ->post(route('checkout.store'), [
                'name' => $student->name,
                'phone' => '01712345678',
                'email' => $student->email,
                'address' => 'Dhaka, Bangladesh',
                'branch_id' => $branch->id,
            ])->assertRedirect();

        $order = Order::where('user_id', $student->id)->latest('id')->firstOrFail();
        $this->assertSame(1, $product->fresh()->reserved_quantity);
        $this->assertTrue((bool) $order->items()->first()->stock_reserved);
        $this->assertSame('pending', $order->status);
    }

    public function test_admin_created_physical_order_reserves_inventory(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $student = User::factory()->create(['role' => 'student', 'is_active' => true]);
        $branch = Branch::create([
            'name' => 'Admin Reservation Branch',
            'code' => 'RSV-02',
            'is_active' => true,
            'payment_methods' => ['bank'],
        ]);
        $product = Product::create([
            'title' => 'Admin Reservation Book',
            'price' => 350,
            'stock_quantity' => 4,
            'is_published' => true,
        ]);

        $this->actingAs($admin)->post(route('admin.orders.store'), [
            'user_id' => $student->id,
            'branch_id' => $branch->id,
            'name' => $student->name,
            'phone' => '01712345678',
            'email' => $student->email,
            'address' => 'Dhaka, Bangladesh',
            'items' => [['type' => 'product', 'id' => $product->id]],
        ])->assertRedirect();

        $order = Order::where('user_id', $student->id)->latest('id')->firstOrFail();
        $this->assertSame(1, $product->fresh()->reserved_quantity);
        $this->assertTrue((bool) $order->items()->first()->stock_reserved);
    }

    public function test_reserved_stock_is_hidden_from_other_buyers_and_released_on_cancellation(): void
    {
        $student = User::factory()->create(['role' => 'student', 'is_active' => true]);
        $product = Product::create([
            'title' => 'Limited Stock Book',
            'price' => 250,
            'stock_quantity' => 3,
            'is_published' => true,
        ]);
        $order = Order::create([
            'user_id' => $student->id,
            'subtotal' => 750,
            'discount' => 0,
            'total' => 750,
            'status' => 'pending',
            'name' => $student->name,
            'phone' => '01712345678',
            'email' => $student->email,
            'address' => 'Dhaka, Bangladesh',
        ]);
        $order->items()->create([
            'item_type' => 'product',
            'item_id' => $product->id,
            'title' => $product->title,
            'price' => 250,
            'qty' => 3,
        ]);

        OrderService::reserveProducts($order);

        $this->assertSame(3, $product->fresh()->reserved_quantity);
        $this->assertSame(0, $product->fresh()->availableQuantity());
        $this->assertNull(Product::published()->find($product->id));

        $anotherOrder = Order::create([
            'user_id' => $student->id,
            'subtotal' => 250,
            'discount' => 0,
            'total' => 250,
            'status' => 'pending',
            'name' => $student->name,
            'phone' => '01712345678',
            'email' => $student->email,
            'address' => 'Dhaka, Bangladesh',
        ]);
        $anotherOrder->items()->create([
            'item_type' => 'product',
            'item_id' => $product->id,
            'title' => $product->title,
            'price' => 250,
            'qty' => 1,
        ]);

        try {
            OrderService::reserveProducts($anotherOrder);
            $this->fail('A second order must not reserve already-held stock.');
        } catch (\\Illuminate\\Validation\\ValidationException) {
            $this->assertSame(3, $product->fresh()->reserved_quantity);
        }

        OrderService::releaseReservations($order);
        $this->assertSame(0, $product->fresh()->reserved_quantity);
        $this->assertSame(3, $product->fresh()->availableQuantity());
        $this->assertFalse($order->items()->first()->fresh()->stock_reserved);
    }

    public function test_reserved_stock_is_consumed_when_order_is_paid_and_never_twice(): void
    {
        $student = User::factory()->create(['role' => 'student', 'is_active' => true]);
        $product = Product::create([
            'title' => 'Reserved Grammar Book',
            'price' => 450,
            'stock_quantity' => 8,
            'is_published' => true,
        ]);
        $order = Order::create([
            'user_id' => $student->id,
            'subtotal' => 900,
            'discount' => 0,
            'total' => 900,
            'status' => 'pending',
            'name' => $student->name,
            'phone' => '01712345678',
            'email' => $student->email,
            'address' => 'Dhaka, Bangladesh',
        ]);
        $item = $order->items()->create([
            'item_type' => 'product',
            'item_id' => $product->id,
            'title' => $product->title,
            'price' => 450,
            'qty' => 2,
        ]);

        OrderService::reserveProducts($order);
        $this->assertSame(2, $product->fresh()->reserved_quantity);

        OrderService::fulfill($order);
        OrderService::fulfill($order);

        $this->assertSame(6, $product->fresh()->stock_quantity);
        $this->assertSame(0, $product->fresh()->reserved_quantity);
        $this->assertFalse($item->fresh()->stock_reserved);
        $this->assertSame('paid', $order->fresh()->status);
    }

    public function test_stale_unpaid_order_expiry_cancels_order_and_releases_reserved_stock(): void
    {
        $student = User::factory()->create(['role' => 'student', 'is_active' => true]);
        $product = Product::create([
            'title' => 'Expiry Test Book',
            'price' => 300,
            'stock_quantity' => 4,
            'is_published' => true,
        ]);
        $order = Order::create([
            'user_id' => $student->id,
            'subtotal' => 300,
            'discount' => 0,
            'total' => 300,
            'status' => 'pending',
            'name' => $student->name,
            'phone' => '01712345678',
            'email' => $student->email,
            'address' => 'Dhaka, Bangladesh',
        ]);
        $order->forceFill([
            'created_at' => now()->subHours(25),
            'updated_at' => now()->subHours(25),
        ])->save();
        $item = $order->items()->create([
            'item_type' => 'product',
            'item_id' => $product->id,
            'title' => $product->title,
            'price' => 300,
            'qty' => 1,
        ]);
        OrderService::reserveProducts($order);

        $expired = OrderService::expireUnpaidOrders(24);

        $this->assertSame(1, $expired);
        $this->assertSame('cancelled', $order->fresh()->status);
        $this->assertSame(0, $product->fresh()->reserved_quantity);
        $this->assertFalse($item->fresh()->stock_reserved);
    }

    public function test_admin_cancellation_releases_reservations_and_reopening_reserves_again(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $student = User::factory()->create(['role' => 'student', 'is_active' => true]);
        $product = Product::create([
            'title' => 'Reopen Stock Book',
            'price' => 300,
            'stock_quantity' => 3,
            'is_published' => true,
        ]);
        $order = Order::create([
            'user_id' => $student->id,
            'subtotal' => 300,
            'discount' => 0,
            'total' => 300,
            'status' => 'pending',
            'name' => $student->name,
            'phone' => '01712345678',
            'email' => $student->email,
            'address' => 'Dhaka, Bangladesh',
        ]);
        $order->items()->create([
            'item_type' => 'product',
            'item_id' => $product->id,
            'title' => $product->title,
            'price' => 300,
            'qty' => 1,
        ]);
        OrderService::reserveProducts($order);

        $this->actingAs($admin)
            ->post(route('admin.orders.status', $order->order_no), ['status' => 'cancelled'])
            ->assertSessionHasNoErrors();
        $this->assertSame(0, $product->fresh()->reserved_quantity);
        $this->assertSame('cancelled', $order->fresh()->status);

        $this->post(route('admin.orders.status', $order->order_no), ['status' => 'pending'])
            ->assertSessionHasNoErrors();
        $this->assertSame(1, $product->fresh()->reserved_quantity);
        $this->assertSame('pending', $order->fresh()->status);
    }

    public function test_expiry_does_not_cancel_orders_with_payment_awaiting_review(): void
    {
        $student = User::factory()->create(['role' => 'student', 'is_active' => true]);
        $product = Product::create([
            'title' => 'Awaiting Review Book',
            'price' => 300,
            'stock_quantity' => 2,
            'is_published' => true,
        ]);
        $order = Order::create([
            'user_id' => $student->id,
            'subtotal' => 300,
            'discount' => 0,
            'total' => 300,
            'status' => 'pending',
            'name' => $student->name,
            'phone' => '01712345678',
            'email' => $student->email,
            'address' => 'Dhaka, Bangladesh',
        ]);
        $order->forceFill(['created_at' => now()->subHours(30), 'updated_at' => now()->subHours(30)])->save();
        $order->items()->create([
            'item_type' => 'product',
            'item_id' => $product->id,
            'title' => $product->title,
            'price' => 300,
            'qty' => 1,
        ]);
        OrderService::reserveProducts($order);
        Payment::create([
            'order_id' => $order->id,
            'gateway' => 'bkash',
            'provider_payment_id' => 'BKASH-PENDING-REVIEW-1',
            'amount' => 300,
            'status' => 'pending',
        ]);

        $this->assertSame(0, OrderService::expireUnpaidOrders(24));
        $this->assertSame('pending', $order->fresh()->status);
        $this->assertSame(1, $product->fresh()->reserved_quantity);
    }

    public function test_admin_cannot_reduce_total_stock_below_reserved_quantity(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $student = User::factory()->create(['role' => 'student', 'is_active' => true]);
        $product = Product::create([
            'title' => 'Reserved Admin Stock',
            'price' => 250,
            'stock_quantity' => 5,
            'is_published' => true,
        ]);
        $order = Order::create([
            'user_id' => $student->id,
            'subtotal' => 500,
            'discount' => 0,
            'total' => 500,
            'status' => 'pending',
            'name' => $student->name,
            'phone' => '01712345678',
            'email' => $student->email,
            'address' => 'Dhaka, Bangladesh',
        ]);
        $order->items()->create([
            'item_type' => 'product',
            'item_id' => $product->id,
            'title' => $product->title,
            'price' => 250,
            'qty' => 2,
        ]);
        OrderService::reserveProducts($order);

        $this->actingAs($admin)
            ->put(route('admin.products.update', $product), [
                'title' => $product->title,
                'slug' => $product->slug,
                'sku' => $product->sku,
                'description' => $product->description,
                'price' => $product->price,
                'stock_quantity' => 1,
                'is_published' => 1,
            ])->assertSessionHasErrors('stock_quantity');

        $this->assertSame(5, $product->fresh()->stock_quantity);
        $this->assertSame(2, $product->fresh()->reserved_quantity);
    }

    public function test_fulfillment_refuses_to_oversell_and_rolls_back_order_status(): void
    {
        $student = User::factory()->create(['role' => 'student', 'is_active' => true]);
        $product = Product::create([
            'title' => 'Limited Workbook',
            'price' => 300,
            'stock_quantity' => 1,
            'is_published' => true,
        ]);
        $order = Order::create([
            'user_id' => $student->id,
            'subtotal' => 600,
            'discount' => 0,
            'total' => 600,
            'status' => 'pending',
            'name' => $student->name,
            'phone' => '01712345678',
            'email' => $student->email,
            'address' => 'Dhaka, Bangladesh',
        ]);
        $order->items()->create([
            'item_type' => 'product',
            'item_id' => $product->id,
            'title' => $product->title,
            'price' => 300,
            'qty' => 2,
        ]);

        try {
            OrderService::fulfill($order);
            $this->fail('Overselling should be rejected.');
        } catch (\Illuminate\Validation\ValidationException) {
            $this->assertSame(1, $product->fresh()->stock_quantity);
            $this->assertSame('pending', $order->fresh()->status);
        }
    }
}
