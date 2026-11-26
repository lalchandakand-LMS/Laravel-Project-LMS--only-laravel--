<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Course;
use App\Models\Ebook;
use App\Models\FlashSale;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminOrderManagementTest extends TestCase
{
    use RefreshDatabase;

    private function setupAdminAndStudent(): array
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $student = User::factory()->create(['role' => 'student', 'is_active' => true]);
        $branch = Branch::create([
            'name' => 'Orders Test Branch',
            'code' => 'ORD-TEST',
            'is_active' => true,
            'payment_methods' => ['bkash', 'cash'],
        ]);
        $student->update(['branch_id' => $branch->id]);

        return [$admin, $student, $branch];
    }

    public function test_admin_can_create_an_unpaid_order_for_a_student_using_catalog_prices(): void
    {
        [$admin, $student, $branch] = $this->setupAdminAndStudent();
        $course = Course::create([
            'title' => 'Admin Order Course', 'slug' => 'admin-order-course',
            'price' => 1200, 'discount_price' => 900, 'is_published' => true,
        ]);

        $this->actingAs($admin)->post(route('admin.orders.store'), [
            'user_id' => $student->id, 'branch_id' => $branch->id,
            'name' => $student->name, 'phone' => '01700000000',
            'email' => $student->email, 'address' => 'Dhaka',
            'items' => [['type' => 'course', 'id' => $course->id]],
        ])->assertRedirect();

        $order = Order::with('items')->firstOrFail();
        $this->assertSame('pending', $order->status);
        $this->assertSame($branch->id, $order->branch_id);
        $this->assertSame(900.0, (float) $order->total);
        $this->assertSame('Admin Order Course', $order->items->first()->title);
        $this->assertDatabaseCount('payments', 0);
    }

    public function test_order_list_filters_by_branch_status_item_type_and_search(): void
    {
        [$admin, $student, $branch] = $this->setupAdminAndStudent();
        $order = Order::create([
            'user_id' => $student->id, 'branch_id' => $branch->id,
            'subtotal' => 500, 'discount' => 0, 'total' => 500,
            'status' => 'pending', 'name' => 'Filterable Student',
            'phone' => '01712345678', 'email' => $student->email,
        ]);
        $order->items()->create(['item_type' => 'course', 'item_id' => 99, 'title' => 'Course item', 'price' => 500, 'qty' => 1]);

        $this->actingAs($admin)->get(route('admin.orders.index', [
            'branch_id' => $branch->id, 'status' => 'pending',
            'item_type' => 'course', 'q' => 'Filterable',
        ]))->assertOk()->assertSee($order->order_no)->assertSee('Filterable Student');
    }

    public function test_admin_can_export_orders_as_csv_with_filtering(): void
    {
        [$admin, $student, $branch] = $this->setupAdminAndStudent();
        $order = Order::create([
            'user_id' => $student->id, 'branch_id' => $branch->id,
            'subtotal' => 500, 'discount' => 0, 'total' => 500,
            'status' => 'pending', 'name' => $student->name,
            'phone' => '01712345678', 'email' => $student->email,
        ]);

        $response = $this->actingAs($admin)->get(route('admin.orders.export', ['branch_id' => $branch->id]));
        $response->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $this->assertStringContainsString($order->order_no, $response->streamedContent());
    }

    public function test_admin_can_edit_an_unpaid_order_and_recalculate_its_items_and_total(): void
    {
        [$admin, $student, $branch] = $this->setupAdminAndStudent();
        $first = Course::create(['title' => 'Original Course', 'slug' => 'original-course', 'price' => 400, 'is_published' => true]);
        $replacement = Course::create(['title' => 'Replacement Course', 'slug' => 'replacement-course', 'price' => 750, 'is_published' => true]);
        $order = Order::create([
            'user_id' => $student->id, 'branch_id' => $branch->id,
            'subtotal' => 400, 'discount' => 0, 'total' => 400,
            'status' => 'pending', 'name' => $student->name,
            'phone' => '01712345678', 'email' => $student->email,
        ]);
        $order->items()->create(['item_type' => 'course', 'item_id' => $first->id, 'title' => $first->title, 'price' => 400, 'qty' => 1]);

        $this->actingAs($admin)->put(route('admin.orders.update', $order->order_no), [
            'name' => 'Updated Student Name', 'phone' => '01799999999',
            'email' => $student->email, 'address' => 'Updated address',
            'items' => [['type' => 'course', 'id' => $replacement->id]],
        ])->assertRedirect(route('admin.orders.show', $order->order_no));

        $this->assertSame('Updated Student Name', $order->fresh()->name);
        $this->assertSame(750.0, (float) $order->fresh()->total);
        $this->assertSame('Replacement Course', $order->fresh()->items()->firstOrFail()->title);
        $this->assertDatabaseCount('payments', 0);
    }

    public function test_orders_with_payment_history_cannot_be_edited(): void
    {
        [$admin, $student, $branch] = $this->setupAdminAndStudent();
        $order = Order::create([
            'user_id' => $student->id, 'branch_id' => $branch->id,
            'subtotal' => 500, 'discount' => 0, 'total' => 500,
            'status' => 'pending', 'name' => $student->name,
            'phone' => '01712345678', 'email' => $student->email,
        ]);
        Payment::create([
            'order_id' => $order->id, 'gateway' => 'bkash',
            'transaction_id' => 'LOCKED-ORDER-PAYMENT', 'amount' => 100, 'status' => 'pending',
        ]);

        $this->actingAs($admin)->get(route('admin.orders.edit', $order->order_no))->assertForbidden();
        $this->actingAs($admin)->put(route('admin.orders.update', $order->order_no), [
            'name' => 'Changed Name', 'phone' => '01700000000',
            'email' => $student->email,
        ])->assertForbidden();
        $this->assertSame($student->name, $order->fresh()->name);
    }

    public function test_non_admin_cannot_create_or_export_orders(): void
    {
        [, $student] = $this->setupAdminAndStudent();
        $this->actingAs($student)->get(route('admin.orders.create'))->assertForbidden();
        $this->actingAs($student)->get(route('admin.orders.export'))->assertForbidden();
    }
    public function test_admin_created_orders_use_active_flash_sale_price_for_ebooks(): void
    {
        [$admin, $student, $branch] = $this->setupAdminAndStudent();
        $ebook = Ebook::create([
            'title' => 'Admin Order Sale E-book',
            'price' => 1200,
            'pages' => 30,
            'is_free' => false,
            'is_published' => true,
        ]);
        $sale = FlashSale::create([
            'name' => 'Admin order e-book sale',
            'type' => 'fixed',
            'value' => 200,
            'starts_at' => now()->subHour(),
            'ends_at' => now()->addHour(),
            'is_active' => true,
        ]);
        $sale->ebooks()->sync([$ebook->id]);

        $this->actingAs($admin)->post(route('admin.orders.store'), [
            'user_id' => $student->id, 'branch_id' => $branch->id,
            'name' => $student->name, 'phone' => '01700000000',
            'email' => $student->email, 'address' => 'Dhaka',
            'items' => [['type' => 'ebook', 'id' => $ebook->id]],
        ])->assertRedirect();

        $order = Order::with('items')->latest('id')->firstOrFail();
        $this->assertSame(1000.0, (float) $order->total);
        $this->assertSame(1000.0, (float) $order->items->first()->price);
    }

}
