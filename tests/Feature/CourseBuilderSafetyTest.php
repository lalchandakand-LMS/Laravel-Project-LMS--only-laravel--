<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\LessonProgress;
use App\Models\Section;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CourseBuilderSafetyTest extends TestCase
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

    public function test_discount_price_must_be_lower_than_regular_price(): void
    {
        $course = Course::firstOrFail();

        $this->actingAs($this->admin())
            ->put(route('admin.courses.update', $course), [
                'title' => $course->title,
                'price' => 1000,
                'discount_price' => 1000,
                'level' => 'beginner',
                'language' => 'Bangla',
            ])
            ->assertSessionHasErrors('discount_price');

        $this->assertNotSame(1000.0, (float) $course->fresh()->discount_price);
    }

    public function test_deleting_a_section_keeps_lessons_and_student_progress(): void
    {
        $course = Course::firstOrFail();
        $section = $course->sections()->create(['title' => 'Safety test section', 'position' => 99]);
        $lesson = $section->lessons()->create(['title' => 'Preserved lesson']);
        $student = User::where('role', 'student')->firstOrFail();

        LessonProgress::create([
            'user_id' => $student->id,
            'lesson_id' => $lesson->id,
            'watched_seconds' => 90,
        ]);

        $this->actingAs($this->admin())
            ->delete(route('admin.sections.destroy', $section))
            ->assertRedirect(route('admin.courses.edit', $course));

        $this->assertSoftDeleted('sections', ['id' => $section->id]);
        $this->assertDatabaseHas('lessons', ['id' => $lesson->id, 'section_id' => null]);
        $this->assertDatabaseHas('lesson_progress', [
            'user_id' => $student->id,
            'lesson_id' => $lesson->id,
            'watched_seconds' => 90,
        ]);
    }

    public function test_course_section_and_lesson_order_can_be_rearranged_only_within_their_parent(): void
    {
        $course = Course::firstOrFail();
        $first = $course->sections()->create(['title' => 'First section', 'position' => 1]);
        $second = $course->sections()->create(['title' => 'Second section', 'position' => 2]);

        $this->actingAs($this->admin())
            ->postJson(route('admin.courses.sections.reorder', $course), [
                'ordered_ids' => [$second->id, $first->id],
            ])
            ->assertOk()
            ->assertJson(['ok' => true]);

        $this->assertSame(1, (int) $second->fresh()->position);
        $this->assertSame(2, (int) $first->fresh()->position);

        $lessonA = $first->lessons()->create(['title' => 'Lesson A']);
        $lessonB = $first->lessons()->create(['title' => 'Lesson B']);

        $this->actingAs($this->admin())
            ->postJson(route('admin.sections.lessons.reorder', $first), [
                'ordered_ids' => [$lessonB->id, $lessonA->id],
            ])
            ->assertOk();

        $this->assertSame(1, (int) $lessonB->fresh()->position);
        $this->assertSame(2, (int) $lessonA->fresh()->position);

        $this->actingAs($this->admin())
            ->postJson(route('admin.sections.lessons.reorder', $second), [
                'ordered_ids' => [$lessonA->id, $lessonB->id],
            ])
            ->assertUnprocessable();
    }

    public function test_free_enrollment_respects_seat_limit_and_prevents_duplicate_seats(): void
    {
        $course = Course::where('price', 0)->firstOrFail();
        $course->update(['is_published' => true, 'seat_limit' => 1]);
        Enrollment::where('course_id', $course->id)->delete();

        $students = User::where('role', 'student')->orderBy('id')->take(2)->get();
        $this->assertCount(2, $students);

        $this->actingAs($students[0])
            ->post(route('courses.enroll-free', $course))
            ->assertRedirect(route('student.learn', $course->slug));

        $this->actingAs($students[0])
            ->post(route('courses.enroll-free', $course))
            ->assertRedirect(route('student.learn', $course->slug));

        $this->actingAs($students[1])
            ->post(route('courses.enroll-free', $course))
            ->assertStatus(409);

        $this->assertSame(1, Enrollment::where('course_id', $course->id)->where('status', 'active')->count());
    }
}
