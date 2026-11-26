<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Course;
use App\Models\Level;
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
        $lesson = $section->lessons()->create(['course_id' => $course->id, 'title' => 'Preserved lesson']);
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

        $orderedSectionIds = $course->sections()->pluck('id')->map(fn ($id) => (int) $id)->all();
        $orderedSectionIds = array_reverse($orderedSectionIds);

        $this->actingAs($this->admin())
            ->postJson(route('admin.courses.sections.reorder', $course), [
                'ordered_ids' => $orderedSectionIds,
            ])
            ->assertOk()
            ->assertJson(['ok' => true]);

        $this->assertSame(array_search($second->id, $orderedSectionIds, true) + 1, (int) $second->fresh()->position);
        $this->assertSame(array_search($first->id, $orderedSectionIds, true) + 1, (int) $first->fresh()->position);

        $lessonA = $first->lessons()->create(['course_id' => $course->id, 'title' => 'Lesson A']);
        $lessonB = $first->lessons()->create(['course_id' => $course->id, 'title' => 'Lesson B']);

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

    public function test_prerequisite_lesson_cannot_be_archived_while_other_lessons_depend_on_it(): void
    {
        $admin = $this->admin();
        $course = Course::create([
            'title' => 'Prerequisite Integrity Course',
            'slug' => 'prerequisite-integrity-course',
            'is_published' => true,
        ]);
        $prerequisite = Lesson::create([
            'course_id' => $course->id,
            'title' => 'Required Foundation Lesson',
            'type' => 'text',
            'position' => 1,
            'is_published' => true,
        ]);
        $dependent = Lesson::create([
            'course_id' => $course->id,
            'title' => 'Dependent Advanced Lesson',
            'type' => 'text',
            'position' => 2,
            'prerequisite_lesson_id' => $prerequisite->id,
            'is_published' => true,
        ]);

        $this->actingAs($admin)
            ->delete(route('admin.lessons.destroy', $prerequisite))
            ->assertStatus(409);

        $this->assertDatabaseHas('lessons', [
            'id' => $prerequisite->id,
            'deleted_at' => null,
        ]);
        $this->assertDatabaseHas('lessons', [
            'id' => $dependent->id,
            'prerequisite_lesson_id' => $prerequisite->id,
            'deleted_at' => null,
        ]);
    }

    public function test_lesson_with_learning_history_cannot_be_moved_to_another_course(): void
    {
        $admin = $this->admin();
        $student = User::where('role', 'student')->firstOrFail();
        $source = Course::create([
            'title' => 'Source Lesson Course',
            'slug' => 'source-lesson-course',
            'is_published' => true,
        ]);
        $target = Course::create([
            'title' => 'Target Lesson Course',
            'slug' => 'target-lesson-course',
            'is_published' => true,
        ]);
        $lesson = Lesson::create([
            'course_id' => $source->id,
            'title' => 'Lesson with Progress',
            'type' => 'text',
            'position' => 1,
            'is_published' => true,
        ]);
        LessonProgress::create([
            'user_id' => $student->id,
            'lesson_id' => $lesson->id,
            'watched_seconds' => 120,
        ]);

        $this->actingAs($admin)
            ->from(route('admin.lessons.edit', $lesson))
            ->put(route('admin.lessons.update', $lesson), [
                'course_id' => $target->id,
                'title' => $lesson->title,
                'type' => $lesson->type,
                'position' => 1,
                'is_published' => 1,
            ])
            ->assertRedirect(route('admin.lessons.edit', $lesson))
            ->assertSessionHasErrors('course_id');

        $this->assertDatabaseHas('lessons', [
            'id' => $lesson->id,
            'course_id' => $source->id,
        ]);
        $this->assertDatabaseHas('lesson_progress', [
            'lesson_id' => $lesson->id,
            'user_id' => $student->id,
            'watched_seconds' => 120,
        ]);
    }

    public function test_course_builder_rejects_archived_categories_and_levels_and_non_teacher_instructors(): void
    {
        $admin = $this->admin();
        $course = Course::firstOrFail();
        $originalCategoryId = $course->category_id;
        $originalLevelId = $course->level_id;

        $inactiveCategory = Category::create([
            'name' => 'Archived course category',
            'is_active' => false,
        ]);
        $inactiveLevel = Level::create([
            'name' => 'Archived course level',
            'is_active' => false,
        ]);
        $student = User::where('role', 'student')->firstOrFail();

        $basePayload = [
            'title' => $course->title,
            'price' => (float) $course->price,
            'language' => 'Bangla',
            'is_published' => 1,
        ];

        $this->actingAs($admin)
            ->put(route('admin.courses.update', $course), $basePayload + [
                'category_ids' => [$inactiveCategory->id],
            ])
            ->assertSessionHasErrors('category_ids.0');

        $this->assertSame($originalCategoryId, $course->fresh()->category_id);

        $this->actingAs($admin)
            ->put(route('admin.courses.update', $course), $basePayload + [
                'level_id' => $inactiveLevel->id,
            ])
            ->assertSessionHasErrors('level_id');

        $this->assertSame($originalLevelId, $course->fresh()->level_id);

        $this->actingAs($admin)
            ->put(route('admin.courses.update', $course), $basePayload + [
                'instructor_id' => $student->id,
            ])
            ->assertSessionHasErrors('instructor_id');

        $this->assertNotSame($student->id, $course->fresh()->instructor_id);
    }

}
