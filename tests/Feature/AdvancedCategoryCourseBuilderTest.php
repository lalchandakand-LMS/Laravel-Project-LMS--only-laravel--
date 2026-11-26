<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Course;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdvancedCategoryCourseBuilderTest extends TestCase
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

    public function test_admin_can_create_nested_categories_and_prevent_circular_parenting(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('admin.categories.store'), [
            'name' => 'English Skills', 'slug' => 'english-skills',
            'description' => 'All English learning tracks.', 'meta_title' => 'English Skills',
            'meta_description' => 'Learn English.', 'position' => 1, 'is_active' => 1,
        ])->assertRedirect(route('admin.categories.index'));

        $parent = Category::where('slug', 'english-skills')->firstOrFail();

        $this->actingAs($admin)->post(route('admin.categories.store'), [
            'name' => 'Grammar', 'parent_id' => $parent->id, 'position' => 2, 'is_active' => 1,
        ])->assertRedirect(route('admin.categories.index'));

        $child = Category::where('name', 'Grammar')->firstOrFail();

        $this->actingAs($admin)->post(route('admin.categories.store'), [
            'name' => 'Tenses', 'parent_id' => $child->id, 'position' => 3, 'is_active' => 1,
        ])->assertRedirect(route('admin.categories.index'));

        $grandchild = Category::where('name', 'Tenses')->firstOrFail();

        $this->actingAs($admin)->put(route('admin.categories.update', $parent), [
            'name' => 'English Skills', 'parent_id' => $grandchild->id, 'is_active' => 1,
        ])->assertSessionHasErrors('parent_id');

        $this->assertSame($parent->id, $child->fresh()->parent_id);
        $this->assertSame($child->id, $grandchild->fresh()->parent_id);
    }

    public function test_admin_can_assign_a_course_to_multiple_categories_and_public_filters_work(): void
    {
        $admin = $this->admin();
        $course = Course::firstOrFail();
        $primary = Category::create(['name' => 'Speaking', 'position' => 1, 'is_active' => true]);
        $secondary = Category::create(['name' => 'IELTS', 'position' => 2, 'is_active' => true]);

        $this->actingAs($admin)->put(route('admin.courses.update', $course), [
            'title' => $course->title,
            'price' => $course->price,
            'level' => 'beginner',
            'language' => 'Bangla',
            'category_ids' => [$primary->id, $secondary->id],
            'is_published' => 1,
        ])->assertRedirect(route('admin.courses.edit', $course));

        $this->assertEqualsCanonicalizing(
            [$primary->id, $secondary->id],
            $course->fresh()->categories()->pluck('categories.id')->all()
        );
        $this->assertSame($primary->id, (int) $course->fresh()->category_id);

        $this->assertDatabaseHas('courses', ['id' => $course->id, 'is_published' => true]);
        $this->assertDatabaseHas('course_category', ['course_id' => $course->id, 'category_id' => $secondary->id]);
        $response = $this->get(route('categories.show', $secondary->slug))->assertOk();
        $response->assertViewHas('courses', fn ($courses) => $courses->getCollection()->contains('id', $course->id));
    }

    public function test_archiving_category_reassigns_primary_courses_and_children_without_hard_deleting(): void
    {
        $admin = $this->admin();
        $parent = Category::create(['name' => 'Root', 'is_active' => true]);
        $category = Category::create(['name' => 'Archive me', 'parent_id' => $parent->id, 'is_active' => true]);
        $child = Category::create(['name' => 'Keep child', 'parent_id' => $category->id, 'is_active' => true]);
        $course = Course::firstOrFail();
        $course->update(['category_id' => $category->id]);

        $this->actingAs($admin)->delete(route('admin.categories.destroy', $category))
            ->assertRedirect(route('admin.categories.index'));

        $this->assertSoftDeleted('categories', ['id' => $category->id]);
        $this->assertSame($parent->id, (int) $child->fresh()->parent_id);
        $this->assertSame($parent->id, (int) $course->fresh()->category_id);
    }

    public function test_instructors_cannot_access_category_builder(): void
    {
        $teacher = User::where('role', 'instructor')->firstOrFail();

        $this->actingAs($teacher)->get(route('admin.categories.index'))->assertForbidden();
        $this->actingAs($teacher)->post(route('admin.categories.store'), [
            'name' => 'Not allowed', 'is_active' => 1,
        ])->assertForbidden();
    }
}
