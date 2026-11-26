<?php

namespace Tests\Feature;

use App\Models\SeoRedirect;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SeoRedirectManagerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_admin_can_create_redirect_and_public_404_is_redirected_with_hit_count(): void
    {
        $admin = User::where('email', 'admin@lms.test')->firstOrFail();

        $this->actingAs($admin)->post(route('admin.seo.redirects.store'), [
            'source_path' => '/old-spoken-english',
            'destination_path' => '/courses/spoken-english',
            'status_code' => 301,
            'is_active' => '1',
        ])->assertRedirect(route('admin.seo.redirects.index'))->assertSessionHasNoErrors();

        $redirect = SeoRedirect::where('source_path', '/old-spoken-english')->firstOrFail();

        $this->get('/old-spoken-english')->assertRedirect('/courses/spoken-english');
        $this->assertSame(1, $redirect->fresh()->hits);
    }

    public function test_admin_can_edit_disable_and_delete_redirect(): void
    {
        $admin = User::where('email', 'admin@lms.test')->firstOrFail();
        $redirect = SeoRedirect::create([
            'source_path' => '/old-course',
            'destination_path' => '/courses/new-course',
            'status_code' => 301,
            'is_active' => true,
        ]);

        $this->actingAs($admin)->put(route('admin.seo.redirects.update', $redirect), [
            'source_path' => '/old-course',
            'destination_path' => '/courses/updated-course',
            'status_code' => 302,
            'is_active' => '0',
        ])->assertRedirect(route('admin.seo.redirects.index'));

        $this->assertDatabaseHas('seo_redirects', [
            'id' => $redirect->id,
            'destination_path' => '/courses/updated-course',
            'status_code' => 302,
            'is_active' => 0,
        ]);

        $this->get('/old-course')->assertNotFound();

        $this->actingAs($admin)->delete(route('admin.seo.redirects.destroy', $redirect))
            ->assertRedirect(route('admin.seo.redirects.index'));
        $this->assertDatabaseMissing('seo_redirects', ['id' => $redirect->id]);
    }

    public function test_redirect_destination_rejects_external_and_unsafe_paths(): void
    {
        $admin = User::where('email', 'admin@lms.test')->firstOrFail();

        $this->actingAs($admin)->from(route('admin.seo.redirects.create'))
            ->post(route('admin.seo.redirects.store'), [
                'source_path' => '/old-course',
                'destination_path' => '//evil.example/steal',
                'status_code' => 301,
            ])->assertSessionHasErrors(['destination_path']);

        $this->actingAs($admin)->from(route('admin.seo.redirects.create'))
            ->post(route('admin.seo.redirects.store'), [
                'source_path' => '/old-course-2',
                'destination_path' => 'https://evil.example/steal',
                'status_code' => 301,
            ])->assertSessionHasErrors(['destination_path']);

        $this->assertDatabaseCount('seo_redirects', 0);
    }

    public function test_redirect_manager_is_admin_only_and_does_not_intercept_private_routes(): void
    {
        $student = User::factory()->create(['role' => 'student', 'is_active' => true]);
        $this->actingAs($student)->get(route('admin.seo.redirects.index'))->assertForbidden();

        SeoRedirect::create([
            'source_path' => '/admin/missing-page',
            'destination_path' => '/courses',
            'status_code' => 301,
            'is_active' => true,
        ]);

        $this->get('/admin/missing-page')->assertNotFound();
    }
}
