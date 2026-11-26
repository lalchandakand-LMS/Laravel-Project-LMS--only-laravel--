<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\CoachingAdmission;
use App\Models\CoachingBatch;
use App\Models\CoachingEnrollment;
use App\Models\Course;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PaymentAdjustment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class GuardianDashboardLedgerAndEligibilityTest extends TestCase
{
    use RefreshDatabase;

    private function branch(string $name): Branch
    {
        return Branch::create([
            'name' => $name,
            'code' => Str::upper(Str::substr(Str::slug($name), 0, 10)).'-'.Str::upper(Str::random(3)),
            'is_active' => true,
            'payment_methods' => ['cash', 'bank', 'bkash', 'nagad'],
        ]);
    }

    private function course(string $title, bool $published = true): Course
    {
        return Course::create([
            'title' => $title,
            'slug' => Str::slug($title).'-'.Str::lower(Str::random(5)),
            'price' => 1000,
            'is_published' => $published,
        ]);
    }

    private function coachingEnrollment(User $student, Branch $branch, Course $course, string $batchName, string $status = 'ongoing', ?\DateTimeInterface $endedAt = null): CoachingEnrollment
    {
        $batch = CoachingBatch::create([
            'branch_id' => $branch->id,
            'course_id' => $course->id,
            'name' => $batchName,
            'code' => 'GDN-'.Str::upper(Str::random(8)),
            'status' => $status,
        ]);
        $admission = CoachingAdmission::create([
            'student_name' => $student->name,
            'student_email' => $student->email,
            'student_phone' => '01700000000',
            'coaching_batch_id' => $batch->id,
            'branch_id' => $branch->id,
            'course_id' => $course->id,
            'student_id' => $student->id,
            'status' => 'admitted',
            'admitted_at' => now(),
        ]);

        return CoachingEnrollment::create([
            'coaching_admission_id' => $admission->id,
            'student_id' => $student->id,
            'coaching_batch_id' => $batch->id,
            'course_id' => $course->id,
            'status' => 'active',
            'admitted_at' => now(),
            'ended_at' => $endedAt,
        ]);
    }

    public function test_guardian_dashboard_counts_only_eligible_coaching_batches_and_calculates_true_order_due(): void
    {
        $guardian = User::factory()->create(['role' => 'guardian', 'is_active' => true]);
        $childBranch = $this->branch('Guardian Child Branch');
        $otherBranch = $this->branch('Guardian Other Branch');
        $child = User::factory()->create([
            'role' => 'student',
            'is_active' => true,
            'branch_id' => $childBranch->id,
        ]);
        $unlinkedChild = User::factory()->create(['role' => 'student', 'is_active' => true]);
        $guardian->children()->attach($child->id, ['relation' => 'father']);

        $publishedCourse = $this->course('Guardian Published Coaching Course');
        $unpublishedCourse = $this->course('Guardian Unpublished Coaching Course', false);

        $this->coachingEnrollment($child, $childBranch, $publishedCourse, 'Guardian Current Branch Batch');
        $this->coachingEnrollment($child, $otherBranch, $publishedCourse, 'Guardian Other Branch Batch');
        $this->coachingEnrollment($child, $childBranch, $publishedCourse, 'Guardian Ended Enrollment Batch', 'ongoing', now()->subDay());
        $this->coachingEnrollment($child, $childBranch, $publishedCourse, 'Guardian Cancelled Batch', 'cancelled');
        $this->coachingEnrollment($child, $childBranch, $unpublishedCourse, 'Guardian Unpublished Course Batch');

        $partiallyPaid = Order::create([
            'user_id' => $child->id,
            'branch_id' => $childBranch->id,
            'subtotal' => 1000,
            'discount' => 0,
            'total' => 1000,
            'status' => 'partially_paid',
            'name' => $child->name,
            'phone' => '01700000000',
            'email' => $child->email,
        ]);
        Payment::create([
            'order_id' => $partiallyPaid->id,
            'gateway' => 'bank',
            'amount' => 300,
            'status' => 'verified',
        ]);
        PaymentAdjustment::create([
            'order_id' => $partiallyPaid->id,
            'type' => 'refund',
            'amount' => 50,
            'reason' => 'Partial refund',
        ]);
        PaymentAdjustment::create([
            'order_id' => $partiallyPaid->id,
            'type' => 'credit',
            'amount' => 25,
            'reason' => 'Approved account credit',
        ]);

        Order::create([
            'user_id' => $child->id,
            'branch_id' => $childBranch->id,
            'subtotal' => 200,
            'discount' => 0,
            'total' => 200,
            'status' => 'pending',
            'name' => $child->name,
            'phone' => '01700000000',
            'email' => $child->email,
        ]);
        Order::create([
            'user_id' => $unlinkedChild->id,
            'subtotal' => 500,
            'discount' => 0,
            'total' => 500,
            'status' => 'pending',
            'name' => $unlinkedChild->name,
            'phone' => '01700000000',
            'email' => $unlinkedChild->email,
        ]);

        $this->actingAs($guardian)->get(route('guardian.dashboard'))
            ->assertOk()
            ->assertViewHas('summary', function ($summary) use ($child) {
                return $summary->count() === 1
                    && $summary->first()['child']->is($child)
                    && $summary->first()['coaching_batches'] === 1
                    && $summary->first()['due'] === 925.0;
            })
            ->assertSee('Guardian Current Branch Batch')
            ->assertDontSee('Guardian Other Branch Batch')
            ->assertDontSee('Guardian Ended Enrollment Batch')
            ->assertDontSee('Guardian Cancelled Batch')
            ->assertDontSee('Guardian Unpublished Course Batch')
            ->assertDontSee($unlinkedChild->name);

        $this->actingAs($guardian)->get(route('guardian.child', $child))->assertOk();
        $this->actingAs($guardian)->get(route('guardian.child', $unlinkedChild))->assertForbidden();
    }
}
