<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\Lesson;
use App\Models\Section;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StudentCoursePlayerVisibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_free_preview_player_sidebar_hides_paid_and_scheduled_lessons(): void
    {
        $student = User::factory()->create([
            'role' => 'student',
            'is_active' => true,
        ]);

        $course = Course::create([
            'title' => 'Preview Visibility Course',
            'slug' => 'preview-visibility-course',
            'price' => 1500,
            'is_published' => true,
        ]);

        $section = Section::create([
            'course_id' => $course->id,
            'title' => 'Course lessons',
            'position' => 1,
        ]);

        $preview = Lesson::create([
            'course_id' => $course->id,
            'section_id' => $section->id,
            'title' => 'Free introduction',
            'type' => 'text',
            'position' => 1,
            'is_free_preview' => true,
            'is_published' => true,
        ]);

        Lesson::create([
            'course_id' => $course->id,
            'section_id' => $section->id,
            'title' => 'Paid members only',
            'type' => 'text',
            'position' => 2,
            'is_free_preview' => false,
            'is_published' => true,
        ]);

        Lesson::create([
            'course_id' => $course->id,
            'section_id' => $section->id,
            'title' => 'Preview scheduled for later',
            'type' => 'text',
            'position' => 3,
            'is_free_preview' => true,
            'is_published' => true,
            'available_at' => now()->addDay(),
        ]);

        $response = $this->actingAs($student)->get(
            route('student.learn.lesson', [$course->slug, $preview->id])
        );

        $response->assertOk()
            ->assertSee('Free introduction')
            ->assertDontSee('Paid members only')
            ->assertDontSee('Preview scheduled for later');
    }
}
