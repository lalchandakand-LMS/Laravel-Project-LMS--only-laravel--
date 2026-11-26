<?php

namespace Tests\Feature;

use App\Models\Ebook;
use App\Models\EbookPurchase;
use App\Models\MediaAsset;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MediaLibraryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_admin_can_upload_reusable_image_and_pdf_assets(): void
    {
        Storage::fake('public');
        $admin = User::where('email', 'admin@lms.test')->firstOrFail();

        $this->actingAs($admin)->post(route('admin.media.store'), [
            'title' => 'Homepage brand mark',
            'alt_text' => 'Alom English Center logo',
            'file' => UploadedFile::fake()->image('brand-mark.png'),
        ])->assertRedirect(route('admin.media.index'));

        $image = MediaAsset::where('title', 'Homepage brand mark')->firstOrFail();
        $this->assertSame('upload', $image->source_type);
        $this->assertSame('public', $image->disk);
        Storage::disk('public')->assertExists($image->path);
        $this->assertStringContainsString('/storage/', $image->publicUrl());

        $this->actingAs($admin)->post(route('admin.media.store'), [
            'title' => 'Student handbook',
            'file' => UploadedFile::fake()->create('student-handbook.pdf', 120, 'application/pdf'),
        ])->assertRedirect(route('admin.media.index'));

        $pdf = MediaAsset::where('title', 'Student handbook')->firstOrFail();
        $this->assertSame('application/pdf', $pdf->mime_type);
        Storage::disk('public')->assertExists($pdf->path);
    }

    public function test_admin_can_save_external_image_or_pdf_links_without_fetching_them(): void
    {
        $admin = User::where('email', 'admin@lms.test')->firstOrFail();

        $this->actingAs($admin)->post(route('admin.media.links.store'), [
            'title' => 'CDN homepage banner',
            'url' => 'https://cdn.example.com/aloms/banner.webp',
            'mime_type' => 'image/webp',
            'alt_text' => 'Students learning English',
        ])->assertRedirect(route('admin.media.index'));

        $asset = MediaAsset::where('title', 'CDN homepage banner')->firstOrFail();
        $this->assertSame('link', $asset->source_type);
        $this->assertSame('https://cdn.example.com/aloms/banner.webp', $asset->publicUrl());
        $this->assertNull($asset->path);
    }

    public function test_media_library_rejects_non_http_external_urls(): void
    {
        $admin = User::where('email', 'admin@lms.test')->firstOrFail();

        $this->actingAs($admin)->post(route('admin.media.links.store'), [
            'title' => 'Unsafe link',
            'url' => 'javascript:alert(1)',
        ])->assertSessionHasErrors('url');

        $this->assertDatabaseCount('media_assets', 0);
    }

    public function test_non_admin_users_cannot_open_or_change_media_library(): void
    {
        $student = User::where('email', 'student@lms.test')->firstOrFail();

        $this->actingAs($student)
            ->get(route('admin.media.index'))
            ->assertForbidden();

        $this->actingAs($student)
            ->post(route('admin.media.links.store'), [
                'title' => 'Not allowed',
                'url' => 'https://example.com/file.pdf',
            ])
            ->assertForbidden();

        $this->assertDatabaseCount('media_assets', 0);
    }

    public function test_admin_can_set_site_logo_and_dashboard_banner_using_external_urls(): void
    {
        $admin = User::where('email', 'admin@lms.test')->firstOrFail();

        $this->actingAs($admin)->post(route('admin.settings.update'), [
            'logo_url' => 'https://cdn.example.com/brand/logo.webp',
            'dashboard_banner_url' => 'https://cdn.example.com/brand/dashboard.jpg',
        ])->assertRedirect();

        $this->assertSame('https://cdn.example.com/brand/logo.webp', Setting::get('logo'));
        $this->assertSame('https://cdn.example.com/brand/dashboard.jpg', Setting::get('dashboard_banner'));
    }

    public function test_media_library_prevents_deleting_an_asset_referenced_by_site_settings(): void
    {
        Storage::fake('public');
        $admin = User::where('email', 'admin@lms.test')->firstOrFail();
        $path = 'media-library/site-logo.png';
        Storage::disk('public')->put($path, 'fake-image');

        $asset = MediaAsset::create([
            'title' => 'Shared site logo',
            'source_type' => 'upload',
            'disk' => 'public',
            'path' => $path,
            'mime_type' => 'image/png',
            'uploaded_by' => $admin->id,
        ]);
        Setting::set('logo', $asset->publicUrl());

        $this->actingAs($admin)
            ->delete(route('admin.media.destroy', $asset))
            ->assertStatus(409);

        $this->assertDatabaseHas('media_assets', ['id' => $asset->id]);
        Storage::disk('public')->assertExists($path);
    }

    public function test_admin_can_delete_unused_media_and_its_uploaded_file(): void
    {
        Storage::fake('public');
        $admin = User::where('email', 'admin@lms.test')->firstOrFail();
        $path = 'media-library/unused-banner.webp';
        Storage::disk('public')->put($path, 'fake-image');

        $asset = MediaAsset::create([
            'title' => 'Unused banner',
            'source_type' => 'upload',
            'disk' => 'public',
            'path' => $path,
            'mime_type' => 'image/webp',
            'uploaded_by' => $admin->id,
        ]);

        $this->actingAs($admin)
            ->delete(route('admin.media.destroy', $asset))
            ->assertRedirect(route('admin.media.index'));

        $this->assertDatabaseMissing('media_assets', ['id' => $asset->id]);
        Storage::disk('public')->assertMissing($path);
    }

    public function test_media_cleanup_preserves_a_shared_file_until_its_last_setting_reference_is_removed(): void
    {
        Storage::fake('public');
        $path = 'courses/shared-brand-banner.jpg';
        Storage::disk('public')->put($path, 'shared-image');
        Setting::set('dashboard_banner', asset('storage/'.$path));

        delete_stored_media('public', $path);
        Storage::disk('public')->assertExists($path);

        Setting::set('dashboard_banner', '');
        delete_stored_media('public', $path);
        Storage::disk('public')->assertMissing($path);
    }

    public function test_media_cleanup_does_not_delete_shared_library_files_or_remote_urls(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('media-library/shared-logo.png', 'shared-image');

        delete_stored_media('public', 'media-library/shared-logo.png');
        delete_stored_media('public', 'https://cdn.example.com/external.jpg');

        Storage::disk('public')->assertExists('media-library/shared-logo.png');
    }

    public function test_media_url_accessors_accept_external_links_for_site_and_course_assets(): void
    {
        $url = 'https://cdn.example.com/aloms/asset.webp';

        $this->assertSame($url, media_url($url));
        $this->assertSame($url, \App\Models\Course::make(['thumbnail' => $url])->thumbnail_url);
        $this->assertSame($url, \App\Models\Slider::make(['image' => $url])->imageUrl());
        $this->assertSame($url, Ebook::make(['cover' => $url])->coverUrl());
        $this->assertSame($url, User::make(['name' => 'Test Student', 'avatar' => $url])->avatarUrl());
    }

}
