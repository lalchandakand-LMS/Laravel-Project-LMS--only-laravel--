<?php

namespace Tests\Feature;

use App\Http\Controllers\CartController;
use App\Models\Course;
use App\Models\Ebook;
use App\Models\EbookPurchase;
use App\Models\Enrollment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StaleCartProductEligibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_cart_rechecks_publication_and_existing_ownership_before_checkout(): void
    {
        $student = User::factory()->create([
            'role' => 'student',
            'is_active' => true,
        ]);

        $unpublishedCourse = Course::create([
            'title' => 'Unpublished Course',
            'slug' => 'unpublished-course',
            'price' => 1000,
            'is_published' => false,
        ]);
        $unpublishedEbook = Ebook::create([
            'title' => 'Unpublished eBook',
            'price' => 500,
            'file' => 'ebook-files/unpublished.pdf',
            'is_free' => false,
            'is_published' => false,
        ]);

        $ownedCourse = Course::create([
            'title' => 'Already Owned Course',
            'slug' => 'already-owned-course',
            'price' => 1000,
            'is_published' => true,
        ]);
        Enrollment::create([
            'user_id' => $student->id,
            'course_id' => $ownedCourse->id,
            'price_paid' => 1000,
            'status' => 'active',
            'enrolled_at' => now(),
        ]);

        $ownedEbook = Ebook::create([
            'title' => 'Already Owned eBook',
            'price' => 500,
            'file' => 'ebook-files/owned.pdf',
            'is_free' => false,
            'is_published' => true,
        ]);
        EbookPurchase::create([
            'user_id' => $student->id,
            'ebook_id' => $ownedEbook->id,
        ]);

        $this->actingAs($student)->withSession([
            'cart' => [
                'course:'.$unpublishedCourse->id,
                'ebook:'.$unpublishedEbook->id,
                'course:'.$ownedCourse->id,
                'ebook:'.$ownedEbook->id,
            ],
        ]);

        $totals = CartController::totals();

        $this->assertSame(0, $totals['count']);
        $this->assertSame([], $totals['items']);
        $this->post(route('checkout.store'), [
            'name' => $student->name,
            'phone' => '01712345678',
            'email' => $student->email,
            'branch_id' => 1,
        ])->assertForbidden();
    }
}
