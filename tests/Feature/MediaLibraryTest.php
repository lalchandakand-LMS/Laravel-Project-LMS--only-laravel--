<?php

namespace Tests\Feature;

use App\Models\Ebook;
use App\Models\EbookPurchase;
use App\Models\Course;
use App\Models\MediaAsset;
use App\Models\Setting;
use App\Models\Category;
use App\Models\Post;
use App\Models\Slider;
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


    public function test_content_uploads_are_registered_in_the_shared_media_library(): void
    {
        Storage::fake('public');
        $admin = User::where('email', 'admin@lms.test')->firstOrFail();
        $course = Course::firstOrFail();

        $this->actingAs($admin)->put(route('admin.courses.update', $course), [
            'title' => $course->title,
            'price' => $course->price,
            'level' => 'beginner',
            'language' => 'Bangla',
            'thumbnail' => UploadedFile::fake()->image('course-cover.png'),
        ])->assertRedirect();

        $this->actingAs($admin)->post(route('admin.categories.store'), [
            'name' => 'Media Library Test Category',
            'is_active' => 1,
            'image' => UploadedFile::fake()->image('category-cover.png'),
        ])->assertRedirect(route('admin.categories.index'));

        $this->actingAs($admin)->post(route('admin.sliders.store'), [
            'title' => 'Media Library Test Slider',
            'image' => UploadedFile::fake()->image('slider-banner.png'),
            'theme' => 'emerald',
            'sort_order' => 2,
            'is_active' => 1,
        ])->assertRedirect(route('admin.sliders.index'));

        $this->actingAs($admin)->post(route('admin.posts.store'), [
            'title' => 'Media Library Test Post',
            'thumbnail' => UploadedFile::fake()->image('post-cover.png'),
            'is_published' => 0,
        ])->assertRedirect();

        $this->actingAs($admin)->post(route('admin.ebooks.store'), [
            'title' => 'Media Library Test E-book',
            'price' => 0,
            'pages' => 10,
            'cover' => UploadedFile::fake()->image('ebook-cover.png'),
            'file' => UploadedFile::fake()->create('ebook.pdf', 20, 'application/pdf'),
            'is_free' => 1,
            'is_published' => 1,
        ])->assertRedirect(route('admin.ebooks.index'));

        $student = User::where('role', 'student')->firstOrFail();
        $this->actingAs($admin)->put(route('admin.users.update', $student), [
            'name' => $student->name,
            'email' => $student->email,
            'phone' => $student->phone,
            'role' => 'student',
            'password' => '',
            'avatar' => UploadedFile::fake()->image('student-avatar.png'),
            'is_active' => 1,
        ])->assertRedirect(route('admin.users.index'));

        foreach ([
            'Course thumbnail',
            'Category image: Media Library Test Category',
            'Slider banner: Media Library Test Slider',
            'Post thumbnail: Media Library Test Post',
            'E-book cover: Media Library Test E-book',
            'Profile photo: '.$student->name,
        ] as $title) {
            $asset = MediaAsset::where('title', $title)->firstOrFail();
            $this->assertSame('public', $asset->disk);
            Storage::disk('public')->assertExists($asset->path);
        }
    }

    public function test_media_library_picker_displays_select_action_for_existing_assets(): void
    {
        $admin = User::where('email', 'admin@lms.test')->firstOrFail();
        $asset = MediaAsset::create([
            'title' => 'Picker image',
            'source_type' => 'link',
            'url' => 'https://cdn.example.com/picker.webp',
            'mime_type' => 'image/webp',
            'uploaded_by' => $admin->id,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.media.index', ['picker' => 1, 'target' => 'thumbnail_url', 'type' => 'image']))
            ->assertOk()
            ->assertSee('Use this file')
            ->assertSee('data-select-media', false)
            ->assertSee('https://cdn.example.com/picker.webp', false);
    }


    public function test_admin_can_upload_multiple_images_to_the_shared_media_library_at_once(): void
    {
        Storage::fake('public');
        $admin = User::where('email', 'admin@lms.test')->firstOrFail();

        $this->actingAs($admin)->post(route('admin.media.store'), [
            'title' => 'Homepage campaign',
            'files' => [
                UploadedFile::fake()->image('banner-one.png'),
                UploadedFile::fake()->image('banner-two.webp'),
                UploadedFile::fake()->image('banner-three.jpg'),
            ],
        ])->assertRedirect(route('admin.media.index'));

        $assets = MediaAsset::where('title', 'like', 'Homepage campaign — %')->get();
        $this->assertCount(3, $assets);
        foreach ($assets as $asset) {
            Storage::disk('public')->assertExists($asset->path);
            $this->assertSame($admin->id, $asset->uploaded_by);
        }
    }

}
