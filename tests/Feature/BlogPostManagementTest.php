<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BlogPostManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        Storage::fake('public');
    }

    public function test_admin_can_create_edit_publish_and_archive_posts(): void
    {
        $admin = User::where('email', 'admin@lms.test')->firstOrFail();
        $category = Category::create(['name' => 'English Tips', 'is_active' => true]);

        $this->actingAs($admin)->post(route('admin.posts.store'), [
            'title' => 'Daily English Tips', 'category_id' => $category->id,
            'excerpt' => 'Short useful tips', 'content' => 'Practice every day.',
            'tags' => 'English, Speaking, English', 'is_published' => '1',
            'thumbnail' => UploadedFile::fake()->image('tip.jpg'),
        ])->assertRedirect();

        $post = Post::withTrashed()->where('title', 'Daily English Tips')->firstOrFail();
        $this->assertTrue($post->is_published);
        $this->assertSame(['English', 'Speaking'], $post->tags);
        Storage::disk('public')->assertExists($post->thumbnail);
        $this->get(route('blog.show', $post))->assertOk()->assertSee('Practice every day.');

        $this->actingAs($admin)->put(route('admin.posts.update', $post), [
            'title' => 'Updated English Tips', 'content' => 'Updated content',
            'category_id' => $category->id, 'is_published' => '0',
        ])->assertRedirect();

        $this->assertFalse($post->fresh()->is_published);
        $this->get(route('blog.show', $post->fresh()))->assertNotFound();

        $this->actingAs($admin)->delete(route('admin.posts.destroy', $post))->assertRedirect();
        $this->assertSoftDeleted('posts', ['id' => $post->id]);
    }

    public function test_public_blog_only_lists_published_posts_and_instructors_cannot_manage_posts(): void
    {
        $admin = User::where('email', 'admin@lms.test')->firstOrFail();
        $instructor = User::where('role', 'instructor')->firstOrFail();
        $published = Post::create(['title' => 'Visible story', 'content' => 'Public', 'author_id' => $admin->id, 'is_published' => true]);
        Post::create(['title' => 'Draft story', 'content' => 'Private', 'author_id' => $admin->id, 'is_published' => false]);

        $this->get(route('blog.index'))->assertOk()->assertSee('Visible story')->assertDontSee('Draft story');
        $this->actingAs($instructor)->get(route('admin.posts.index'))->assertForbidden();
        $this->get(route('blog.show', $published))->assertOk();
    }
}