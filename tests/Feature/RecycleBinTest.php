<?php

namespace Tests\Feature;

use App\Models\Slider;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class RecycleBinTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_admin_can_restore_a_deleted_slider_and_deletion_actor_is_recorded(): void
    {
        $admin = User::where('email', 'admin@lms.test')->firstOrFail();
        $slider = Slider::create([
            'title' => 'Recoverable homepage banner',
            'subtitle' => 'Test slide',
            'button_text' => 'Explore',
            'button_url' => '/courses',
            'theme' => 'emerald',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $this->actingAs($admin)
            ->delete(route('admin.sliders.destroy', $slider))
            ->assertRedirect();

        $this->assertSoftDeleted('sliders', [
            'id' => $slider->id,
            'deleted_by' => $admin->id,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.recycle-bin.index', ['type' => 'sliders']))
            ->assertOk()
            ->assertSee('Recoverable homepage banner');

        $this->post(route('admin.recycle-bin.restore', ['type' => 'sliders', 'id' => $slider->id]))
            ->assertRedirect();

        $this->assertDatabaseHas('sliders', [
            'id' => $slider->id,
            'deleted_at' => null,
            'deleted_by' => null,
        ]);
    }

    public function test_admin_can_move_a_student_account_to_recycle_bin_and_restore_it(): void
    {
        $admin = User::where('email', 'admin@lms.test')->firstOrFail();
        $student = User::where('email', 'student@lms.test')->firstOrFail();

        $this->actingAs($admin)
            ->delete(route('admin.users.destroy', $student))
            ->assertRedirect();

        $this->assertSoftDeleted('users', [
            'id' => $student->id,
            'deleted_by' => $admin->id,
        ]);

        $this->post(route('admin.recycle-bin.restore', ['type' => 'users', 'id' => $student->id]))
            ->assertRedirect();

        $this->assertDatabaseHas('users', ['id' => $student->id, 'deleted_at' => null]);
    }

    public function test_permanent_delete_is_blocked_when_course_has_enrolment_history(): void
    {
        $admin = User::where('email', 'admin@lms.test')->firstOrFail();
        $student = User::where('email', 'student@lms.test')->firstOrFail();
        $course = Course::create(['title' => 'Protected course history', 'slug' => 'protected-course-history']);

        Enrollment::create([
            'user_id' => $student->id,
            'course_id' => $course->id,
            'status' => 'active',
            'price_paid' => 100,
        ]);

        $this->actingAs($admin)
            ->delete(route('admin.courses.destroy', $course))
            ->assertRedirect();

        $this->delete(route('admin.recycle-bin.destroy', ['type' => 'courses', 'id' => $course->id]))
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertSoftDeleted('courses', ['id' => $course->id]);
        $this->assertDatabaseHas('enrollments', ['course_id' => $course->id, 'user_id' => $student->id]);
    }

    public function test_expired_safe_records_are_pruned_but_linked_course_history_is_retained(): void
    {
        $student = User::where('email', 'student@lms.test')->firstOrFail();
        $slider = Slider::create(['title' => 'Expired slider', 'is_active' => false]);
        $slider->delete();
        DB::table('sliders')->where('id', $slider->id)->update(['deleted_at' => now()->subDays(31)]);

        $course = Course::create(['title' => 'Protected old course', 'slug' => 'protected-old-course']);
        Enrollment::create([
            'user_id' => $student->id,
            'course_id' => $course->id,
            'status' => 'active',
            'price_paid' => 50,
        ]);
        $course->delete();
        DB::table('courses')->where('id', $course->id)->update(['deleted_at' => now()->subDays(31)]);

        Artisan::call('recycle-bin:prune', ['--days' => 30]);

        $this->assertDatabaseMissing('sliders', ['id' => $slider->id]);
        $this->assertSoftDeleted('courses', ['id' => $course->id]);
        $this->assertDatabaseHas('enrollments', ['course_id' => $course->id, 'user_id' => $student->id]);
    }

    public function test_recycle_bin_rejects_unknown_model_types(): void
    {
        $admin = User::where('email', 'admin@lms.test')->firstOrFail();

        $this->actingAs($admin)
            ->get('/admin/recycle-bin/not-a-model')
            ->assertNotFound();
    }

    public function test_slider_media_is_retained_until_permanent_recycle_bin_deletion(): void
    {
        Storage::fake('public');
        $admin = User::where('email', 'admin@lms.test')->firstOrFail();
        $image = 'sliders/recycle-bin-retention.png';
        Storage::disk('public')->put($image, 'image-content');
        $slider = Slider::create([
            'title' => 'Media retention slider',
            'image' => $image,
            'is_active' => false,
            'theme' => 'emerald',
        ]);

        $this->actingAs($admin)->delete(route('admin.sliders.destroy', $slider))->assertRedirect();
        Storage::disk('public')->assertExists($image);

        $this->delete(route('admin.recycle-bin.destroy', ['type' => 'sliders', 'id' => $slider->id]))
            ->assertRedirect()
            ->assertSessionHas('success');
        Storage::disk('public')->assertMissing($image);
    }

    public function test_expired_slider_pruning_deletes_its_media_file(): void
    {
        Storage::fake('public');
        $image = 'sliders/expired-recycle-bin.png';
        Storage::disk('public')->put($image, 'image-content');
        $slider = Slider::create([
            'title' => 'Expired slider with media',
            'image' => $image,
            'is_active' => false,
            'theme' => 'emerald',
        ]);
        $slider->delete();
        DB::table('sliders')->where('id', $slider->id)->update(['deleted_at' => now()->subDays(31)]);

        Artisan::call('recycle-bin:prune', ['--days' => 30]);

        $this->assertDatabaseMissing('sliders', ['id' => $slider->id]);
        Storage::disk('public')->assertMissing($image);
    }

    public function test_branch_with_coaching_finance_history_cannot_be_permanently_deleted_or_pruned(): void
    {
        $admin = User::where('email', 'admin@lms.test')->firstOrFail();
        $branch = Branch::create([
            'name' => 'Protected Coaching Branch',
            'code' => 'PROTECTED-COACH',
            'is_active' => true,
            'payment_methods' => ['bkash'],
        ]);
        $course = Course::create(['title' => 'Protected Coaching Course', 'slug' => 'protected-coaching-course']);
        $batch = CoachingBatch::create([
            'branch_id' => $branch->id,
            'course_id' => $course->id,
            'name' => 'Protected Coaching Batch',
            'code' => 'PROTECTED-COACH-BATCH',
            'admission_fee' => 500,
            'monthly_fee' => 1500,
            'status' => 'ongoing',
        ]);

        $branch->delete();

        $this->actingAs($admin)->delete(route('admin.recycle-bin.destroy', ['type' => 'branches', 'id' => $branch->id]))
            ->assertRedirect()
            ->assertSessionHas('error');

        DB::table('branches')->where('id', $branch->id)->update(['deleted_at' => now()->subDays(31)]);
        Artisan::call('recycle-bin:prune', ['--days' => 30]);

        $this->assertSoftDeleted('branches', ['id' => $branch->id]);
        $this->assertDatabaseHas('coaching_batches', ['id' => $batch->id, 'branch_id' => $branch->id]);
    }

}
