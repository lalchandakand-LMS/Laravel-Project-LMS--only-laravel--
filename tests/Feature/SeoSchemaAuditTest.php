<?php

namespace Tests\\Feature;

use App\\Models\\Course;
use App\\Models\\Post;
use App\\Models\\User;
use Illuminate\\Foundation\\Testing\\RefreshDatabase;
use Tests\\TestCase;

class SeoSchemaAuditTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_public_layout_outputs_educational_organization_and_website_schema(): void
    {
        $this->get('/')->assertOk()
            ->assertSee('"@type":"EducationalOrganization"', false)
            ->assertSee('"@type":"WebSite"', false)
            ->assertSee('"@type":"SearchAction"', false);
    }

    public function test_course_page_outputs_course_schema_and_uses_meta_title(): void
    {
        $course = Course::where('is_published', true)->firstOrFail();
        $course->update([
            'meta_title' => 'Custom Spoken English SEO Title',
            'meta_description' => 'A structured English course with guided practice.',
        ]);

        $this->get(route('courses.show', $course))->assertOk()
            ->assertSee('"@type":"Course"', false)
            ->assertSee('Custom Spoken English SEO Title')
            ->assertSee('A structured English course with guided practice.');
    }

    public function test_blog_page_outputs_article_schema_and_meta_description(): void
    {
        $admin = User::where('email', 'admin@lms.test')->firstOrFail();
        $post = Post::create([
            'author_id' => $admin->id,
            'title' => 'SEO structured data article',
            'slug' => 'seo-structured-data-article',
            'excerpt' => 'A useful article excerpt.',
            'content' => 'Article body.',
            'meta_title' => 'SEO article title',
            'meta_description' => 'SEO article description.',
            'is_published' => true,
            'published_at' => now(),
        ]);

        $this->get(route('blog.show', $post))->assertOk()
            ->assertSee('"@type":"Article"', false)
            ->assertSee('SEO article title')
            ->assertSee('SEO article description.');
    }

    public function test_admin_seo_audit_lists_published_courses_missing_metadata(): void
    {
        $admin = User::where('email', 'admin@lms.test')->firstOrFail();
        $course = Course::where('is_published', true)->firstOrFail();
        $course->update(['meta_title' => null, 'meta_description' => null]);

        $this->actingAs($admin)->get(route('admin.seo.index'))->assertOk()
            ->assertSee('On-page SEO audit')
            ->assertSee('Published courses missing title or description')
            ->assertSee($course->title)
            ->assertSee('Edit SEO fields');
    }
}
