<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Category;
use App\Models\Course;
use App\Models\FlashSale;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FlashSaleManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    private function admin(): User
    {
        return User::where('email', 'admin@lms.test')->firstOrFail();
    }

    private function studentWithoutCourse(): array
    {
        $student = User::where('email', 'student@lms.test')->firstOrFail();
        $course = Course::where('price', '>', 0)
            ->whereDoesntHave('enrollments', fn ($query) => $query->where('user_id', $student->id))
            ->orderBy('id')->firstOrFail();

        $course->update(['price' => 1000, 'discount_price' => 900]);

        return [$student, $course];
    }

    private function campaignPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Early bird flash sale',
            'type' => 'percent',
            'value' => 25,
            'description' => 'Limited-time course discount',
            'starts_at' => now()->subMinute()->format('Y-m-d H:i:s'),
            'ends_at' => now()->addDay()->format('Y-m-d H:i:s'),
            'is_active' => 1,
        ], $overrides);
    }

    public function test_admin_can_create_and_manage_course_flash_sale_and_price_flows_to_checkout(): void
    {
        [$student, $course] = $this->studentWithoutCourse();

        $this->actingAs($this->admin())->post(route('admin.flash-sales.store'), $this->campaignPayload([
            'course_ids' => [$course->id],
        ]))->assertRedirect(route('admin.flash-sales.index'));

        $sale = FlashSale::where('name', 'Early bird flash sale')->firstOrFail();
        $this->assertDatabaseHas('flash_sale_courses', [
            'flash_sale_id' => $sale->id, 'course_id' => $course->id,
        ]);
        $this->assertSame(750.0, $course->fresh()->final_price);
        $this->assertSame(25, $course->fresh()->discount_percent);

        $this->actingAs($this->admin())->patch(route('admin.flash-sales.toggle', $sale))->assertRedirect();
        $this->assertSame(900.0, $course->fresh()->final_price);
        $this->actingAs($this->admin())->patch(route('admin.flash-sales.toggle', $sale->fresh()))->assertRedirect();
        $this->assertSame(750.0, $course->fresh()->final_price);

        $this->actingAs($student)->get(route('courses.show', $course->slug))
            ->assertOk()->assertSee('Early bird flash sale')->assertSee('750');
        $this->actingAs($student)->post(route('cart.add', ['type' => 'course', 'id' => $course->id]))
            ->assertRedirect(route('cart.index'));
        $this->actingAs($student)->get(route('cart.index'))
            ->assertOk()->assertSee('Early bird flash sale')->assertSee('750');

        $branch = $student->branch;
        if (! $branch) {
            $branch = Branch::create([
                'name' => 'Flash Sale Test Branch', 'code' => 'FSALE',
                'is_active' => true, 'payment_methods' => ['bkash'],
            ]);
            $student->update(['branch_id' => $branch->id]);
        } else {
            $branch->update(['is_active' => true, 'payment_methods' => ['bkash']]);
        }

        $this->actingAs($student)->post(route('checkout.store'), [
            'name' => $student->name, 'phone' => '01700000000',
            'email' => $student->email, 'branch_id' => $branch->id,
        ])->assertRedirect();

        $order = Order::where('user_id', $student->id)->latest('id')->firstOrFail();
        $this->assertSame(750.0, (float) $order->items()->where('item_type', 'course')->firstOrFail()->price);

        $this->actingAs($this->admin())->put(route('admin.flash-sales.update', $sale), $this->campaignPayload([
            'name' => 'Updated course offer', 'type' => 'fixed', 'value' => 200,
            'course_ids' => [$course->id],
        ]))->assertRedirect(route('admin.flash-sales.index'));
        $this->assertSame(800.0, $course->fresh()->final_price);

        $this->actingAs($this->admin())->delete(route('admin.flash-sales.destroy', $sale->fresh()))
            ->assertRedirect();
        $this->assertSoftDeleted('flash_sales', ['id' => $sale->id]);
        $this->assertSame(900.0, $course->fresh()->final_price);

        $this->actingAs($this->admin())->post(route('admin.recycle-bin.restore', [
            'type' => 'flash-sales', 'id' => $sale->id,
        ]))->assertRedirect();
        $this->assertNotSoftDeleted('flash_sales', ['id' => $sale->id]);
        $this->assertSame(800.0, $course->fresh()->final_price);
    }

    public function test_category_flash_sale_applies_to_primary_and_additional_course_categories(): void
    {
        [$student, $course] = $this->studentWithoutCourse();
        $category = $course->category ?: Category::firstOrFail();

        $this->actingAs($this->admin())->post(route('admin.flash-sales.store'), $this->campaignPayload([
            'type' => 'fixed', 'value' => 150, 'category_ids' => [$category->id],
        ]))->assertRedirect();

        $this->assertSame(850.0, $course->fresh()->final_price);
    }

    public function test_scheduled_inactive_and_expired_sales_never_change_course_price(): void
    {
        [, $course] = $this->studentWithoutCourse();

        $future = FlashSale::create($this->campaignPayload([
            'name' => 'Future sale', 'starts_at' => now()->addHour(), 'ends_at' => now()->addDays(2),
        ]));
        $future->courses()->sync([$course->id]);
        $this->assertSame(900.0, $course->fresh()->final_price);

        $future->update(['starts_at' => now()->subHour(), 'ends_at' => now()->addDay(), 'is_active' => false]);
        $this->assertSame(900.0, $course->fresh()->final_price);

        $future->update(['is_active' => true, 'ends_at' => now()->subSecond()]);
        $this->assertSame(900.0, $course->fresh()->final_price);
    }

    public function test_flash_sale_requires_valid_discount_and_at_least_one_target(): void
    {
        $this->actingAs($this->admin())->post(route('admin.flash-sales.store'), $this->campaignPayload([
            'value' => 150, 'course_ids' => [],
        ]))->assertSessionHasErrors(['value', 'course_ids']);

        $this->actingAs($this->admin())->post(route('admin.flash-sales.store'), $this->campaignPayload([
            'course_ids' => [], 'category_ids' => [],
        ]))->assertSessionHasErrors('course_ids');
    }

    public function test_instructors_cannot_manage_flash_sales(): void
    {
        $instructor = User::factory()->create(['role' => 'instructor', 'is_active' => true]);

        $this->actingAs($instructor)->get(route('admin.flash-sales.index'))->assertForbidden();
        $this->actingAs($instructor)->post(route('admin.flash-sales.store'), $this->campaignPayload())
            ->assertForbidden();
    }
}
