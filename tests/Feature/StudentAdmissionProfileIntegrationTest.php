<?php

namespace Tests\Feature;

use App\Models\CoachingAdmission;
use App\Models\ProfileDocument;
use App\Models\StudentProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class StudentAdmissionProfileIntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_admission_submission_saves_academic_details_and_private_documents_transfer_to_student_profile_on_approval(): void
    {
        Storage::fake('local');
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $branch = \App\Models\Branch::create(['name' => 'Dhaka', 'code' => 'DHK', 'is_active' => true]);
        $course = \App\Models\Course::create(['title' => 'Spoken English', 'price' => 3000, 'is_published' => true]);
        $batch = \App\Models\CoachingBatch::create([
            'name' => 'Morning Batch', 'code' => 'MORN-ADMIT', 'branch_id' => $branch->id,
            'course_id' => $course->id, 'admission_fee' => 0, 'monthly_fee' => 1000, 'status' => 'upcoming',
        ]);

        $this->post(route('coaching-admission.store'), [
            'student_name' => 'Admission Student',
            'student_email' => 'admission.student@example.com',
            'student_phone' => '01700000011',
            'student_date_of_birth' => '2012-04-10',
            'student_gender' => 'female',
            'school_name' => 'Dhaka Model School',
            'academic_class' => 'Class 7',
            'academic_roll' => '17',
            'academic_session' => '2026',
            'academic_group' => 'Science',
            'present_address' => 'Present address',
            'permanent_address' => 'Permanent address',
            'guardian_name' => 'Parent One',
            'guardian_phone' => '01700000012',
            'guardian_email' => 'parent.one@example.com',
            'quota_type' => 'General',
            'coaching_batch_id' => $batch->id,
            'student_photo' => UploadedFile::fake()->image('student.jpg'),
            'identity_document' => UploadedFile::fake()->create('birth.pdf', 100, 'application/pdf'),
        ])->assertRedirect(route('coaching-admission.create'));

        $admission = CoachingAdmission::where('student_email', 'admission.student@example.com')->firstOrFail();
        $this->assertDatabaseHas('coaching_admissions', [
            'id' => $admission->id,
            'school_name' => 'Dhaka Model School',
            'academic_class' => 'Class 7',
            'academic_roll' => '17',
        ]);
        $this->assertCount(2, $admission->documents);

        $this->actingAs($admin)->post(route('admin.coaching-admissions.approve', $admission))->assertStatus(422);

        foreach ($admission->documents as $document) {
            $this->get(route('admin.coaching-admissions.documents.download', [$admission, $document]))->assertOk();
            $this->post(route('admin.coaching-admissions.documents.review', [$admission, $document]), [
                'status' => 'approved',
                'review_note' => 'Verified original document.',
            ])->assertRedirect();
        }

        $this->post(route('admin.coaching-admissions.approve', $admission))->assertRedirect();

        $student = User::where('email', 'admission.student@example.com')->firstOrFail();
        $profile = $student->studentProfile;
        $this->assertInstanceOf(StudentProfile::class, $profile);
        $this->assertSame('Dhaka Model School', $profile->school_name);
        $this->assertSame('Class 7', $profile->academic_class);
        $this->assertSame('Parent One', $profile->guardian_name);
        $this->assertSame('active', $profile->status);
        $this->assertSame(2, $profile->documents()->count());
        $this->assertSame(2, ProfileDocument::where('documentable_type', $profile->getMorphClass())
            ->where('documentable_id', $profile->id)->count());

        foreach ($profile->documents as $document) {
            Storage::disk('local')->assertExists($document->path);
        }
    }
}
