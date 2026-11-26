<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Course;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategoryArchiveIntegrityTest extends TestCase
{
    use RefreshDatabase;

    public function test_archiving_a_category_reassigns_course_pivot_links_and_preserves_other_categories(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $parent = Category::create([
            'name' => 'Parent English',
            'slug' => 'parent-english',
            'is_active' => true,
        ]);
        $child = Category::create([
            'parent_id' => $parent->id,
            'name' => 'Child Grammar',
            'slug' => 'child-grammar',
            'is_active' => true,
        ]);
        $other = Category::create([
            'name' => 'Other Skills',
            'slug' => 'other-skills',
            'is_active' => true,
        ]);

        $alreadyParentLinked = Course::create([
            'category_id' => $child->id,
            'title' => 'Already parent linked course',
            'slug' => 'already-parent-linked-course',
            'price' => 100,
            'is_published' => true,
        ]);
        $alreadyParentLinked->categories()->attach([$child->id, $parent->id, $other->id]);

        $childOnly = Course::create([
            'category_id' => $child->id,
            'title' => 'Child only course',
            'slug' => 'child-only-course',
            'price' => 100,
            'is_published' => true,
        ]);
        $childOnly->categories()->attach([$child->id, $other->id]);

        $this->actingAs($admin)
            ->delete(route('admin.categories.destroy', $child))
            ->assertRedirect(route('admin.categories.index'));

        $this->assertSoftDeleted('categories', ['id' => $child->id]);
        $this->assertDatabaseHas('categories', [
            'id' => $child->id,
            'parent_id' => $parent->id,
        ]);

        foreach ([$alreadyParentLinked, $childOnly] as $course) {
            $this->assertDatabaseHas('course_category', [
                'course_id' => $course->id,
                'category_id' => $parent->id,
            ]);
            $this->assertDatabaseHas('course_category', [
                'course_id' => $course->id,
                'category_id' => $other->id,
            ]);
            $this->assertDatabaseMissing('course_category', [
                'course_id' => $course->id,
                'category_id' => $child->id,
            ]);
        }

        $this->assertSame(2, $alreadyParentLinked->fresh()->categories()->count());
        $this->assertSame(2, $childOnly->fresh()->categories()->count());
        $this->assertSame($parent->id, $childOnly->fresh()->category_id);
    }

    public function test_archiving_a_root_category_detaches_only_its_pivot_links(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $root = Category::create([
            'name' => 'Root to archive',
            'slug' => 'root-to-archive',
            'is_active' => true,
        ]);
        $other = Category::create([
            'name' => 'Retained category',
            'slug' => 'retained-category',
            'is_active' => true,
        ]);
        $course = Course::create([
            'category_id' => $root->id,
            'title' => 'Root category course',
            'slug' => 'root-category-course',
            'price' => 100,
            'is_published' => true,
        ]);
        $course->categories()->attach([$root->id, $other->id]);

        $this->actingAs($admin)
            ->delete(route('admin.categories.destroy', $root))
            ->assertRedirect(route('admin.categories.index'));

        $this->assertSoftDeleted('categories', ['id' => $root->id]);
        $this->assertDatabaseMissing('course_category', [
            'course_id' => $course->id,
            'category_id' => $root->id,
        ]);
        $this->assertDatabaseHas('course_category', [
            'course_id' => $course->id,
            'category_id' => $other->id,
        ]);
        $this->assertNull($course->fresh()->category_id);
        $this->assertSame([$other->id], $course->fresh()->categories()->pluck('categories.id')->all());
    }
}
