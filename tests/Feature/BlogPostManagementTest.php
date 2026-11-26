<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Course;
use App\Models\Ebook;
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

        $this->actingAs($admin)->delete(route('admin.posts.destroy', $post->fresh()))->assertRedirect();
        $this->assertSoftDeleted('posts', ['id' => $post->id]);
    }


    public function test_sitemap_lists_public_content_and_excludes_drafts_and_unpublished_records(): void
    {
        $category = Category::create([
            'name' => 'Public Sitemap Category',
            'slug' => 'public-sitemap-category',
            'is_active' => true,
        ]);
        $hiddenCategory = Category::create([
            'name' => 'Hidden Sitemap Category',
            'slug' => 'hidden-sitemap-category',
            'is_active' => false,
        ]);

        $publishedCourse = Course::create([
            'category_id' => $category->id,
            'title' => 'Public Sitemap Course',
            'slug' => 'public-sitemap-course',
            'price' => 1000,
            'is_published' => true,
        ]);
        Course::create([
            'category_id' => $hiddenCategory->id,
            'title' => 'Unpublished Sitemap Course',
            'slug' => 'unpublished-sitemap-course',
            'price' => 1000,
            'is_published' => false,
        ]);

        $admin = User::where('email', 'admin@lms.test')->firstOrFail();
        $publishedPost = Post::create([
            'title' => 'Public Sitemap Article',
            'content' => 'Published article content.',
            'author_id' => $admin->id,
            'is_published' => true,
        ]);
        $draftPost = Post::create([
            'title' => 'Private Sitemap Draft',
            'content' => 'Draft content.',
            'author_id' => $admin->id,
            'is_published' => false,
        ]);

        $publishedEbook = Ebook::create([
            'title' => 'Public Sitemap E-book',
            'slug' => 'public-sitemap-ebook',
            'description' => 'A public e-book',
            'price' => 100,
            'is_published' => true,
        ]);
        Ebook::create([
            'title' => 'Hidden Sitemap E-book',
            'slug' => 'hidden-sitemap-ebook',
            'description' => 'An unpublished e-book',
            'price' => 100,
            'is_published' => false,
        ]);

        $response = $this->get(route('sitemap'));

        $response->assertOk()
            ->assertHeader('Content-Type', 'application/xml; charset=UTF-8')
            ->assertSee(route('courses.show', $publishedCourse->slug), false)
            ->assertSee(route('categories.show', $category->slug), false)
            ->assertSee(route('blog.show', $publishedPost->slug), false)
            ->assertSee(route('ebooks.show', $publishedEbook->slug), false)
            ->assertSee(route('home'), false)
            ->assertDontSee('unpublished-sitemap-course')
            ->assertDontSee('hidden-sitemap-category')
            ->assertDontSee($draftPost->slug)
            ->assertDontSee('hidden-sitemap-ebook')
            ->assertDontSee('Private Sitemap Draft');
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