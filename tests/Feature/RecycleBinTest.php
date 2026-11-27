<?php

namespace Tests\Feature;

use App\Models\Slider;
use App\Models\Course;
use App\Models\Branch;
use App\Models\CoachingBatch;
use App\Models\AttendanceSession;
use App\Models\LiveClass;
use App\Models\Lesson;
use App\Models\Enrollment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
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


    public function test_user_permanent_delete_is_blocked_when_complaint_library_ai_or_staff_history_exists(): void
    {
        $admin = User::where('email', 'admin@lms.test')->firstOrFail();
        $user = User::factory()->create(['role' => 'branch_manager', 'is_active' => false]);
        $now = now();

        $complaintId = DB::table('complaints')->insertGetId([
            'tracking_code' => 'CMP-RECYCLE-'.strtoupper(substr(uniqid(), -8)),
            'user_id' => $user->id,
            'category' => 'administration',
            'subject' => 'Retain complaint history',
            'body' => 'This complaint must remain attached to the account.',
            'priority' => 'normal',
            'status' => 'submitted',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('complaint_replies')->insert([
            'complaint_id' => $complaintId,
            'user_id' => $user->id,
            'body' => 'Retain reply history.',
            'is_internal' => false,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $bookId = DB::table('library_books')->insertGetId([
            'title' => 'Protected library history',
            'author' => 'Test Author',
            'total_copies' => 1,
            'available_copies' => 1,
            'is_active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('library_loans')->insert([
            'book_id' => $bookId,
            'student_id' => $user->id,
            'status' => 'returned',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $runId = DB::table('ai_agent_runs')->insertGetId([
            'user_id' => $user->id,
            'agent_type' => 'support',
            'prompt' => 'Retain AI audit history.',
            'status' => 'completed',
            'provider' => 'gemini',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('ai_action_proposals')->insert([
            'ai_agent_run_id' => $runId,
            'user_id' => $user->id,
            'action_type' => 'create_announcement_draft',
            'payload' => json_encode(['title' => 'Retain proposal']),
            'status' => 'pending',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $staffProfileId = DB::table('staff_profiles')->insertGetId([
            'user_id' => $user->id,
            'employment_status' => 'inactive',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('staff_payrolls')->insert([
            'staff_profile_id' => $staffProfileId,
            'payroll_month' => now()->format('Y-m'),
            'basic_salary' => 10000,
            'net_salary' => 10000,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $user->delete();

        $this->actingAs($admin)
            ->delete(route('admin.recycle-bin.destroy', ['type' => 'users', 'id' => $user->id]))
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertSoftDeleted('users', ['id' => $user->id]);
        $this->assertDatabaseHas('complaints', ['id' => $complaintId, 'user_id' => $user->id]);
        $this->assertDatabaseHas('complaint_replies', ['complaint_id' => $complaintId, 'user_id' => $user->id]);
        $this->assertDatabaseHas('library_loans', ['student_id' => $user->id, 'book_id' => $bookId]);
        $this->assertDatabaseHas('ai_agent_runs', ['id' => $runId, 'user_id' => $user->id]);
        $this->assertDatabaseHas('ai_action_proposals', ['user_id' => $user->id]);
        $this->assertDatabaseHas('staff_profiles', ['id' => $staffProfileId, 'user_id' => $user->id]);
        $this->assertDatabaseHas('staff_payrolls', ['staff_profile_id' => $staffProfileId]);
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


    public function test_lesson_attachment_is_retained_on_archive_and_removed_only_on_permanent_delete(): void
    {
        Storage::fake('local');
        $admin = User::where('email', 'admin@lms.test')->firstOrFail();
        $course = Course::create(['title' => 'Lesson Media Course', 'slug' => 'lesson-media-course']);
        $path = 'lessons/recycle-bin-lesson.pdf';
        Storage::disk('local')->put($path, 'private lesson file');
        $lesson = Lesson::create([
            'course_id' => $course->id,
            'title' => 'Recoverable PDF lesson',
            'type' => 'pdf',
            'attachment' => $path,
        ]);

        $this->actingAs($admin)->delete(route('admin.lessons.destroy', $lesson))->assertRedirect();
        Storage::disk('local')->assertExists($path);

        $this->post(route('admin.recycle-bin.restore', ['type' => 'lessons', 'id' => $lesson->id]))
            ->assertRedirect();
        Storage::disk('local')->assertExists($path);

        $this->delete(route('admin.lessons.destroy', $lesson))->assertRedirect();
        $this->delete(route('admin.recycle-bin.destroy', ['type' => 'lessons', 'id' => $lesson->id]))
            ->assertRedirect()
            ->assertSessionHas('success');
        Storage::disk('local')->assertMissing($path);
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


    public function test_live_class_with_attendance_history_cannot_be_permanently_deleted_or_pruned(): void
    {
        $admin = User::where('email', 'admin@lms.test')->firstOrFail();
        $course = Course::create(['title' => 'Attendance History Course', 'slug' => 'attendance-history-course']);
        $liveClass = LiveClass::create([
            'course_id' => $course->id,
            'title' => 'Attendance history live class',
            'platform' => 'Zoom',
            'starts_at' => now()->subDays(40),
            'duration_minutes' => 60,
            'is_published' => true,
        ]);
        $session = AttendanceSession::create([
            'live_class_id' => $liveClass->id,
            'course_id' => $course->id,
            'instructor_id' => $admin->id,
            'topic' => $liveClass->title,
            'starts_at' => $liveClass->starts_at,
            'ends_at' => $liveClass->endsAt(),
            'status' => 'approved',
            'mode' => 'online',
        ]);

        $this->actingAs($admin)->delete(route('admin.live-classes.destroy', $liveClass))->assertRedirect();
        $this->delete(route('admin.recycle-bin.destroy', ['type' => 'live-classes', 'id' => $liveClass->id]))
            ->assertRedirect()
            ->assertSessionHas('error');

        DB::table('live_classes')->where('id', $liveClass->id)->update(['deleted_at' => now()->subDays(31)]);
        Artisan::call('recycle-bin:prune', ['--days' => 30]);

        $this->assertSoftDeleted('live_classes', ['id' => $liveClass->id]);
        $this->assertDatabaseHas('attendance_sessions', [
            'id' => $session->id,
            'live_class_id' => $liveClass->id,
            'status' => 'approved',
        ]);
    }


    public function test_prerequisite_lesson_cannot_be_permanently_purged_while_a_dependent_lesson_references_it(): void
    {
        $admin = User::where('email', 'admin@lms.test')->firstOrFail();
        $course = Course::create(['title' => 'Retained Prerequisite Course', 'slug' => 'retained-prerequisite-course']);
        $prerequisite = Lesson::create([
            'course_id' => $course->id,
            'title' => 'Archived prerequisite lesson',
            'type' => 'text',
            'position' => 1,
        ]);
        $dependent = Lesson::create([
            'course_id' => $course->id,
            'title' => 'Dependent lesson',
            'type' => 'text',
            'position' => 2,
            'prerequisite_lesson_id' => $prerequisite->id,
        ]);

        // Simulate a legacy archived prerequisite that predates the controller guard.
        $prerequisite->delete();

        $this->actingAs($admin)
            ->delete(route('admin.recycle-bin.destroy', ['type' => 'lessons', 'id' => $prerequisite->id]))
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertSoftDeleted('lessons', ['id' => $prerequisite->id]);
        $this->assertDatabaseHas('lessons', [
            'id' => $dependent->id,
            'prerequisite_lesson_id' => $prerequisite->id,
        ]);
    }


    public function test_prerequisite_lesson_with_dependent_record_is_not_pruned(): void
    {
        $course = Course::create(['title' => 'Prerequisite Prune Course', 'slug' => 'prerequisite-prune-course']);
        $prerequisite = Lesson::create([
            'course_id' => $course->id,
            'title' => 'Expired archived prerequisite',
            'type' => 'text',
            'position' => 1,
        ]);
        $dependent = Lesson::create([
            'course_id' => $course->id,
            'title' => 'Current dependent lesson',
            'type' => 'text',
            'position' => 2,
            'prerequisite_lesson_id' => $prerequisite->id,
        ]);
        $prerequisite->delete();
        DB::table('lessons')->where('id', $prerequisite->id)->update(['deleted_at' => now()->subDays(31)]);

        Artisan::call('recycle-bin:prune', ['--days' => 30]);

        $this->assertSoftDeleted('lessons', ['id' => $prerequisite->id]);
        $this->assertDatabaseHas('lessons', [
            'id' => $dependent->id,
            'prerequisite_lesson_id' => $prerequisite->id,
        ]);
    }


    public function test_dependent_lesson_must_be_restored_after_its_prerequisite(): void
    {
        $admin = User::where('email', 'admin@lms.test')->firstOrFail();
        $course = Course::create(['title' => 'Restore Prerequisite Course', 'slug' => 'restore-prerequisite-course']);
        $prerequisite = Lesson::create([
            'course_id' => $course->id,
            'title' => 'Restore prerequisite first',
            'type' => 'text',
            'position' => 1,
        ]);
        $dependent = Lesson::create([
            'course_id' => $course->id,
            'title' => 'Restore dependent second',
            'type' => 'text',
            'position' => 2,
            'prerequisite_lesson_id' => $prerequisite->id,
        ]);

        $this->actingAs($admin)->delete(route('admin.lessons.destroy', $dependent))->assertRedirect();
        $this->delete(route('admin.lessons.destroy', $prerequisite))->assertRedirect();

        $this->post(route('admin.recycle-bin.restore', ['type' => 'lessons', 'id' => $dependent->id]))
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertSoftDeleted('lessons', ['id' => $dependent->id]);
        $this->post(route('admin.recycle-bin.restore', ['type' => 'lessons', 'id' => $prerequisite->id]))
            ->assertRedirect();
        $this->post(route('admin.recycle-bin.restore', ['type' => 'lessons', 'id' => $dependent->id]))
            ->assertRedirect();

        $this->assertDatabaseHas('lessons', ['id' => $prerequisite->id, 'deleted_at' => null]);
        $this->assertDatabaseHas('lessons', ['id' => $dependent->id, 'deleted_at' => null]);
    }


    public function test_user_permanent_delete_is_blocked_when_assigned_complaint_history_exists(): void
    {
        $admin = User::where('email', 'admin@lms.test')->firstOrFail();
        $assignee = User::factory()->create(['role' => 'branch_manager', 'is_active' => false]);
        $complaintOwner = User::factory()->create(['role' => 'student']);
        $now = now();

        $complaintId = DB::table('complaints')->insertGetId([
            'tracking_code' => 'CMP-ASSIGNED-'.strtoupper(substr(uniqid(), -8)),
            'user_id' => $complaintOwner->id,
            'assigned_to' => $assignee->id,
            'category' => 'administration',
            'subject' => 'Retain assignment audit history',
            'body' => 'The assigned staff identity must remain available.',
            'priority' => 'normal',
            'status' => 'submitted',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $assignee->delete();

        $this->actingAs($admin)
            ->delete(route('admin.recycle-bin.destroy', ['type' => 'users', 'id' => $assignee->id]))
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertSoftDeleted('users', ['id' => $assignee->id]);
        $this->assertDatabaseHas('complaints', [
            'id' => $complaintId,
            'assigned_to' => $assignee->id,
        ]);
    }

    public function test_user_permanent_delete_is_blocked_when_payroll_payment_actor_history_exists(): void
    {
        $admin = User::where('email', 'admin@lms.test')->firstOrFail();
        $paymentActor = User::factory()->create(['role' => 'branch_manager', 'is_active' => false]);
        $staffUser = User::factory()->create(['role' => 'teacher']);
        $now = now();

        $staffProfileId = DB::table('staff_profiles')->insertGetId([
            'user_id' => $staffUser->id,
            'employment_status' => 'active',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $payrollId = DB::table('staff_payrolls')->insertGetId([
            'staff_profile_id' => $staffProfileId,
            'payroll_month' => now()->format('Y-m'),
            'basic_salary' => 12000,
            'net_salary' => 12000,
            'status' => 'paid',
            'paid_at' => now()->toDateString(),
            'paid_by' => $paymentActor->id,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $paymentActor->delete();

        $this->actingAs($admin)
            ->delete(route('admin.recycle-bin.destroy', ['type' => 'users', 'id' => $paymentActor->id]))
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertSoftDeleted('users', ['id' => $paymentActor->id]);
        $this->assertDatabaseHas('staff_payrolls', [
            'id' => $payrollId,
            'paid_by' => $paymentActor->id,
        ]);
    }


    public function test_user_permanent_delete_preserves_messages_and_staff_audit_records(): void
    {
        $admin = User::where('email', 'admin@lms.test')->firstOrFail();
        $actor = User::factory()->create(['role' => 'branch_manager', 'is_active' => false]);
        $staffMember = User::factory()->create(['role' => 'teacher']);
        $documentOwner = User::factory()->create(['role' => 'student']);
        $now = now();

        $messageId = DB::table('messages')->insertGetId([
            'sender_id' => $actor->id,
            'recipient_id' => $admin->id,
            'body' => 'Retain sender attribution.',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $leaveId = DB::table('staff_leave_requests')->insertGetId([
            'user_id' => $staffMember->id,
            'start_date' => now()->addDays(2)->toDateString(),
            'end_date' => now()->addDays(3)->toDateString(),
            'leave_type' => 'annual',
            'reason' => 'Retain reviewer attribution.',
            'status' => 'approved',
            'reviewed_by' => $actor->id,
            'reviewed_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $documentId = DB::table('profile_documents')->insertGetId([
            'documentable_type' => User::class,
            'documentable_id' => $documentOwner->id,
            'document_type' => 'identity',
            'disk' => 'local',
            'path' => 'profile-documents/audit-test.pdf',
            'original_name' => 'audit-test.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => 128,
            'status' => 'approved',
            'uploaded_by' => $actor->id,
            'reviewed_by' => $actor->id,
            'reviewed_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $ownedDocumentId = DB::table('profile_documents')->insertGetId([
            'documentable_type' => User::class,
            'documentable_id' => $actor->id,
            'document_type' => 'identity',
            'disk' => 'local',
            'path' => 'profile-documents/user-owned-audit-test.pdf',
            'original_name' => 'user-owned-audit-test.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => 128,
            'status' => 'approved',
            'uploaded_by' => $admin->id,
            'reviewed_by' => $admin->id,
            'reviewed_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $actor->delete();

        $this->actingAs($admin)
            ->delete(route('admin.recycle-bin.destroy', ['type' => 'users', 'id' => $actor->id]))
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertSoftDeleted('users', ['id' => $actor->id]);
        $this->assertDatabaseHas('messages', ['id' => $messageId, 'sender_id' => $actor->id]);
        $this->assertDatabaseHas('staff_leave_requests', ['id' => $leaveId, 'reviewed_by' => $actor->id]);
        $this->assertDatabaseHas('profile_documents', [
            'id' => $ownedDocumentId,
            'documentable_id' => $actor->id,
        ]);
        $this->assertDatabaseHas('profile_documents', [
            'id' => $documentId,
            'uploaded_by' => $actor->id,
            'reviewed_by' => $actor->id,
        ]);
    }


    public function test_user_permanent_delete_preserves_marketing_and_learning_analytics_history(): void
    {
        $admin = User::where('email', 'admin@lms.test')->firstOrFail();
        $actor = User::factory()->create(['role' => 'branch_manager', 'is_active' => false]);
        $leadOwner = User::factory()->create(['role' => 'student']);
        $course = Course::create(['title' => 'Analytics Retention Course', 'slug' => 'analytics-retention-course']);
        $now = now();

        $leadId = DB::table('marketing_leads')->insertGetId([
            'user_id' => $leadOwner->id,
            'name' => 'Audit Lead',
            'email' => 'audit-lead@example.test',
            'message' => 'Retain marketing assignment history.',
            'source' => 'website',
            'status' => 'new',
            'assigned_to' => $actor->id,
            'converted_user_id' => $actor->id,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $campaignId = DB::table('marketing_campaigns')->insertGetId([
            'created_by' => $actor->id,
            'name' => 'Audit Campaign',
            'slug' => 'audit-campaign-'.strtolower(substr(uniqid(), -8)),
            'channel' => 'email',
            'source' => 'newsletter',
            'medium' => 'email',
            'destination_url' => 'https://example.test/courses',
            'status' => 'draft',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $viewId = DB::table('course_views')->insertGetId([
            'course_id' => $course->id,
            'user_id' => $actor->id,
            'visitor_key' => 'recycle-audit-'.strtolower(substr(uniqid(), -10)),
            'viewed_on' => now()->toDateString(),
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $eventId = DB::table('gamification_events')->insertGetId([
            'user_id' => $actor->id,
            'event_type' => 'course_completed',
            'event_key' => 'recycle-audit-'.strtolower(substr(uniqid(), -10)),
            'points' => 10,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $assetId = DB::table('media_assets')->insertGetId([
            'title' => 'Audit media asset',
            'source_type' => 'upload',
            'uploaded_by' => $actor->id,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $actor->delete();

        $this->actingAs($admin)
            ->delete(route('admin.recycle-bin.destroy', ['type' => 'users', 'id' => $actor->id]))
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertSoftDeleted('users', ['id' => $actor->id]);
        $this->assertDatabaseHas('marketing_leads', ['id' => $leadId, 'assigned_to' => $actor->id, 'converted_user_id' => $actor->id]);
        $this->assertDatabaseHas('marketing_campaigns', ['id' => $campaignId, 'created_by' => $actor->id]);
        $this->assertDatabaseHas('course_views', ['id' => $viewId, 'user_id' => $actor->id]);
        $this->assertDatabaseHas('gamification_events', ['id' => $eventId, 'user_id' => $actor->id]);
        $this->assertDatabaseHas('media_assets', ['id' => $assetId, 'uploaded_by' => $actor->id]);
    }

}
