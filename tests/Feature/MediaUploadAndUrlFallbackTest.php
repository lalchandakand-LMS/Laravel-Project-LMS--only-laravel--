<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\Ebook;
use App\Models\EbookPurchase;
use App\Models\Enrollment;
use App\Models\Setting;
use App\Models\Slider;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MediaUploadAndUrlFallbackTest extends TestCase
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

    private function student(): User
    {
        return User::where('role', 'student')->firstOrFail();
    }

    public function test_course_thumbnail_and_slider_images_accept_hosted_urls(): void
    {
        $course = Course::firstOrFail();

        $this->actingAs($this->admin())
            ->put(route('admin.courses.update', $course), [
                'title' => $course->title,
                'price' => $course->price,
                'level' => 'beginner',
                'language' => 'Bangla',
                'thumbnail_url' => 'https://cdn.example.com/course-cover.webp',
            ])
            ->assertRedirect(route('admin.courses.edit', $course->id));

        $this->assertSame('https://cdn.example.com/course-cover.webp', $course->fresh()->thumbnail);
        $this->assertSame('https://cdn.example.com/course-cover.webp', $course->fresh()->thumbnail_url);

        $this->actingAs($this->admin())
            ->post(route('admin.sliders.store'), [
                'title' => 'New intake',
                'image_url' => 'https://cdn.example.com/home-banner.webp',
                'button_text' => 'Apply',
                'button_url' => '/admission',
                'theme' => 'emerald',
                'sort_order' => 1,
                'is_active' => 1,
            ])
            ->assertRedirect(route('admin.sliders.index'));

        $slider = Slider::where('title', 'New intake')->firstOrFail();
        $this->assertSame('https://cdn.example.com/home-banner.webp', $slider->imageUrl());
    }

    public function test_ebook_cover_and_pdf_urls_are_saved_and_pdf_stays_purchase_gated(): void
    {
        $this->actingAs($this->admin())
            ->post(route('admin.ebooks.store'), [
                'title' => 'Remote PDF Handbook',
                'description' => 'External hosted book.',
                'price' => 100,
                'pages' => 80,
                'cover_url' => 'https://cdn.example.com/handbook-cover.webp',
                'file_url' => 'https://cdn.example.com/handbook.pdf',
                'is_published' => 1,
            ])
            ->assertRedirect(route('admin.ebooks.index'));

        $ebook = Ebook::where('title', 'Remote PDF Handbook')->firstOrFail();
        $this->assertSame('https://cdn.example.com/handbook-cover.webp', $ebook->coverUrl());

        $this->actingAs($this->student())
            ->get(route('student.ebooks.file', $ebook->slug))
            ->assertForbidden();

        EbookPurchase::create([
            'user_id' => $this->student()->id,
            'ebook_id' => $ebook->id,
        ]);

        $this->actingAs($this->student())
            ->get(route('student.ebooks.file', $ebook->slug))
            ->assertRedirect('https://cdn.example.com/handbook.pdf');
    }

    public function test_settings_accept_external_logo_and_dashboard_banner_urls(): void
    {
        $this->actingAs($this->admin())
            ->post(route('admin.settings.update'), [
                'logo_url' => 'https://cdn.example.com/aloms-logo.png',
                'dashboard_banner_url' => 'https://cdn.example.com/dashboard.webp',
            ])
            ->assertRedirect();

        $this->assertSame('https://cdn.example.com/aloms-logo.png', Setting::get('logo'));
        $this->assertSame('https://cdn.example.com/dashboard.webp', Setting::get('dashboard_banner'));
    }

    public function test_admin_and_student_can_set_profile_photo_urls(): void
    {
        $student = $this->student();

        $this->actingAs($this->admin())
            ->put(route('admin.users.update', $student), [
                'name' => $student->name,
                'email' => $student->email,
                'phone' => $student->phone,
                'role' => 'student',
                'bio' => 'Student profile.',
                'avatar_url' => 'https://cdn.example.com/admin-set-avatar.webp',
                'password' => '',
                'is_active' => 1,
            ])
            ->assertRedirect(route('admin.users.index'));

        $this->assertSame('https://cdn.example.com/admin-set-avatar.webp', $student->fresh()->avatarUrl());

        $this->actingAs($student->fresh())
            ->put(route('student.profile.update'), [
                'name' => $student->name,
                'phone' => $student->phone,
                'bio' => 'Updated by student.',
                'avatar_url' => 'https://cdn.example.com/student-avatar.webp',
            ])
            ->assertRedirect();

        $this->assertSame('https://cdn.example.com/student-avatar.webp', $student->fresh()->avatarUrl());
    }

    public function test_lesson_attachment_url_redirect_remains_protected_by_enrollment_access(): void
    {
        $course = Course::whereHas('lessons')->firstOrFail();
        $lesson = $course->lessons()->firstOrFail();
        $course->update(['is_published' => true]);
        $lesson->update(['is_published' => true, 'is_free_preview' => false]);

        $this->actingAs($this->admin())
            ->put(route('admin.lessons.update', $lesson), [
                'course_id' => $course->id,
                'section_id' => $lesson->section_id,
                'title' => $lesson->title,
                'type' => 'pdf',
                'video_url' => $lesson->video_url,
                'content' => $lesson->content,
                'duration_minutes' => $lesson->duration_minutes,
                'position' => $lesson->position,
                'attachment_url' => 'https://cdn.example.com/lesson-notes.pdf',
                'is_published' => 1,
            ])
            ->assertRedirect(route('admin.courses.edit', $course));

        $this->actingAs($this->student())
            ->get(route('student.learn.attachment', [$course->slug, $lesson->id]))
            ->assertForbidden();

        Enrollment::updateOrCreate(
            ['user_id' => $this->student()->id, 'course_id' => $course->id],
            ['status' => 'active', 'enrolled_at' => now(), 'expires_at' => null]
        );

        $this->actingAs($this->student())
            ->get(route('student.learn.attachment', [$course->slug, $lesson->id]))
            ->assertRedirect('https://cdn.example.com/lesson-notes.pdf');
    }

    public function test_unsafe_media_schemes_are_rejected(): void
    {
        $course = Course::firstOrFail();

        $this->actingAs($this->admin())
            ->from(route('admin.courses.edit', $course))
            ->put(route('admin.courses.update', $course), [
                'title' => $course->title,
                'price' => $course->price,
                'level' => 'beginner',
                'language' => 'Bangla',
                'thumbnail_url' => 'javascript:alert(1)',
            ])
            ->assertSessionHasErrors('thumbnail_url');

        $this->assertNotSame('javascript:alert(1)', $course->fresh()->thumbnail);
    }
}
