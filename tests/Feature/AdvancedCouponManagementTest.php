<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Category;
use App\Models\Coupon;
use App\Models\Course;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdvancedCouponManagementTest extends TestCase
{
    use RefreshDatabase;

    private function makeBranch(): Branch
    {
        return Branch::create([
            'name' => 'Coupon Test Branch',
            'code' => 'CPN-TEST',
            'is_active' => true,
            'payment_methods' => ['bkash', 'cash'],
        ]);
    }

    private function makeCourse(?int $categoryId = null): Course
    {
        return Course::create([
            'category_id' => $categoryId,
            'title' => 'Coupon Test Course',
            'slug' => 'coupon-test-course',
            'price' => 1000,
            'is_published' => true,
        ]);
    }

    public function test_coupon_discount_is_capped_and_never_exceeds_eligible_amount(): void
    {
        $coupon = Coupon::create([
            'code' => 'CAP100',
            'type' => 'percent',
            'value' => 50,
            'max_discount' => 100,
            'is_active' => true,
        ]);

        $this->assertSame(100.0, $coupon->discountFor(1000));
        $this->assertSame(50.0, $coupon->discountFor(100));
    }

    public function test_targeted_coupon_only_discounts_matching_course_category_and_ebook_items(): void
    {
        $category = Category::create(['name' => 'Coupon Category', 'slug' => 'coupon-category', 'is_active' => true]);
        $course = $this->makeCourse($category->id);
        $coupon = Coupon::create([
            'code' => 'CAT20',
            'type' => 'percent',
            'value' => 20,
            'category_ids' => [$category->id],
            'is_active' => true,
        ]);

        $items = [
            ['type' => 'course', 'id' => $course->id, 'price' => 1000],
            ['type' => 'ebook', 'id' => 88, 'price' => 500],
        ];

        $this->assertSame(1000.0, $coupon->eligibleSubtotal($items));
        $this->assertSame(200.0, $coupon->discountFor($coupon->eligibleSubtotal($items)));
    }

    public function test_coupon_branch_and_per_student_limits_are_enforced_and_cancelled_orders_release_reservations(): void
    {
        $student = User::factory()->create(['role' => 'student', 'is_active' => true]);
        $branch = $this->makeBranch();
        $coupon = Coupon::create([
            'code' => 'ONEUSE',
            'type' => 'fixed',
            'value' => 100,
            'branch_id' => $branch->id,
            'per_user_limit' => 1,
            'usage_limit' => 1,
            'is_active' => true,
        ]);

        $this->assertTrue($coupon->isValidFor(500, $student, [], $branch->id));
        $this->assertFalse($coupon->isValidFor(500, $student, [], $branch->id + 1));

        $order = Order::create([
            'user_id' => $student->id,
            'branch_id' => $branch->id,
            'coupon_id' => $coupon->id,
            'subtotal' => 500,
            'discount' => 100,
            'total' => 400,
            'status' => 'pending',
        ]);

        $this->assertFalse($coupon->fresh()->isValidFor(500, $student, [], $branch->id));
        $order->update(['status' => 'cancelled']);
        $this->assertTrue($coupon->fresh()->isValidFor(500, $student, [], $branch->id));
    }

    public function test_checkout_revalidates_coupon_and_reserves_its_usage_without_incrementing_legacy_counter(): void
    {
        $student = User::factory()->create(['role' => 'student', 'is_active' => true]);
        $branch = $this->makeBranch();
        $course = $this->makeCourse();
        Coupon::create([
            'code' => 'CHECKOUT10',
            'type' => 'percent',
            'value' => 10,
            'usage_limit' => 1,
            'per_user_limit' => 1,
            'is_active' => true,
        ]);

        $this->actingAs($student)->withSession(['cart' => ['course:'.$course->id]])
            ->post(route('cart.coupon'), ['code' => 'checkout10'])
            ->assertSessionHasNoErrors();

        $this->post(route('checkout.store'), [
            'name' => $student->name,
            'phone' => '01712345678',
            'email' => $student->email,
            'branch_id' => $branch->id,
        ])->assertRedirect();

        $order = Order::where('user_id', $student->id)->firstOrFail();
        $this->assertSame(100.0, (float) $order->discount);
        $this->assertSame('pending', $order->status);
        $this->assertSame(1, $order->coupon->reservedUsageCount());
        $this->assertSame(0, (int) $order->coupon->used_count);
    }

    public function test_admin_can_view_and_export_coupon_usage_and_cannot_create_over_one_hundred_percent_coupon(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $student = User::factory()->create(['role' => 'student', 'is_active' => true]);
        $branch = $this->makeBranch();
        $coupon = Coupon::create(['code' => 'REPORT', 'type' => 'fixed', 'value' => 50, 'is_active' => true]);
        $order = Order::create([
            'user_id' => $student->id,
            'branch_id' => $branch->id,
            'coupon_id' => $coupon->id,
            'subtotal' => 500,
            'discount' => 50,
            'total' => 450,
            'status' => 'pending',
        ]);

        $this->actingAs($admin)->get(route('admin.coupons.usage'))
            ->assertOk()->assertSee($order->order_no)->assertSee('REPORT');
        $this->get(route('admin.coupons.usage.export'))
            ->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8');

        $this->post(route('admin.coupons.store'), [
            'code' => 'TOOMUCH',
            'type' => 'percent',
            'value' => 101,
            'is_active' => 1,
        ])->assertSessionHasErrors('value');
    }

    public function test_coupon_with_order_history_cannot_be_deleted(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $student = User::factory()->create(['role' => 'student', 'is_active' => true]);
        $coupon = Coupon::create(['code' => 'KEEPHISTORY', 'type' => 'fixed', 'value' => 50, 'is_active' => true]);
        Order::create([
            'user_id' => $student->id,
            'coupon_id' => $coupon->id,
            'subtotal' => 100,
            'discount' => 50,
            'total' => 50,
            'status' => 'paid',
        ]);

        $this->actingAs($admin)->delete(route('admin.coupons.destroy', $coupon))
            ->assertRedirect()->assertSessionHas('error');
        $this->assertDatabaseHas('coupons', ['id' => $coupon->id]);
    }
}
