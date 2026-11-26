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

    public function test_course_player_resumes_the_most_recently_visited_incomplete_lesson(): void
    {
        $student = User::factory()->create(['role' => 'student', 'is_active' => true]);
        $course = Course::create([
            'title' => 'Resume Learning Course',
            'slug' => 'resume-learning-course',
            'price' => 1500,
            'is_published' => true,
        ]);
        $lessons = collect(range(1, 3))->map(fn ($position) => Lesson::create([
            'course_id' => $course->id,
            'title' => 'Resume Lesson '.$position,
            'type' => 'text',
            'position' => $position,
            'is_published' => true,
        ]));

        \App\Models\Enrollment::create([
            'user_id' => $student->id,
            'course_id' => $course->id,
            'status' => 'active',
            'price_paid' => 1500,
            'enrolled_at' => now(),
            'expires_at' => now()->addDays(30),
        ]);

        \App\Models\LessonProgress::create([
            'user_id' => $student->id,
            'lesson_id' => $lessons[0]->id,
            'completed_at' => now(),
        ]);
        $olderProgress = \App\Models\LessonProgress::create([
            'user_id' => $student->id,
            'lesson_id' => $lessons[1]->id,
            'watched_seconds' => 45,
        ]);
        \Illuminate\Support\Facades\DB::table('lesson_progress')
            ->where('id', $olderProgress->id)
            ->update(['updated_at' => now()->subMinutes(5)]);
        \App\Models\LessonProgress::create([
            'user_id' => $student->id,
            'lesson_id' => $lessons[2]->id,
            'watched_seconds' => 90,
        ]);

        $this->actingAs($student)
            ->get(route('student.learn', $course->slug))
            ->assertRedirect(route('student.learn.lesson', [$course->slug, $lessons[2]->id]));
    }

    public function test_course_player_opens_the_last_lesson_when_all_accessible_lessons_are_complete(): void
    {
        $student = User::factory()->create(['role' => 'student', 'is_active' => true]);
        $course = Course::create([
            'title' => 'Finished Learning Course',
            'slug' => 'finished-learning-course',
            'price' => 1500,
            'is_published' => true,
        ]);
        $first = Lesson::create([
            'course_id' => $course->id,
            'title' => 'Finished Lesson One',
            'type' => 'text',
            'position' => 1,
            'is_published' => true,
        ]);
        $last = Lesson::create([
            'course_id' => $course->id,
            'title' => 'Finished Lesson Two',
            'type' => 'text',
            'position' => 2,
            'is_published' => true,
        ]);

        \App\Models\Enrollment::create([
            'user_id' => $student->id,
            'course_id' => $course->id,
            'status' => 'active',
            'price_paid' => 1500,
            'enrolled_at' => now(),
            'expires_at' => now()->addDays(30),
        ]);
        foreach ([$first, $last] as $lesson) {
            \App\Models\LessonProgress::create([
                'user_id' => $student->id,
                'lesson_id' => $lesson->id,
                'completed_at' => now(),
            ]);
        }

        $this->actingAs($student)
            ->get(route('student.learn', $course->slug))
            ->assertRedirect(route('student.learn.lesson', [$course->slug, $last->id]));
    }
}
