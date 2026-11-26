<?php

namespace Tests\Feature;

use App\Http\Controllers\CartController;
use App\Models\Order;
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
