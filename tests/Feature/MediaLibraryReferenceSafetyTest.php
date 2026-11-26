<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\MediaAsset;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MediaLibraryReferenceSafetyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        Storage::fake('public');
    }

    public function test_media_library_asset_cannot_be_deleted_while_category_uses_its_url(): void
    {
        $admin = User::where('email', 'admin@lms.test')->firstOrFail();
        $path = 'media-library/category-image.webp';
        Storage::disk('public')->put($path, 'fake image bytes');

        $media = MediaAsset::create([
            'title' => 'Category artwork',
            'source_type' => 'upload',
            'disk' => 'public',
            'path' => $path,
            'mime_type' => 'image/webp',
            'uploaded_by' => $admin->id,
        ]);

        Category::create([
            'name' => 'Media-linked category',
            'image' => $media->publicUrl(),
            'is_active' => true,
        ]);

        $this->actingAs($admin)
            ->delete(route('admin.media.destroy', $media))
            ->assertStatus(409);

        Storage::disk('public')->assertExists($path);
        $this->assertDatabaseHas('media_assets', ['id' => $media->id]);
    }

    public function test_category_reference_prevents_cleanup_until_reference_is_removed(): void
    {
        $path = 'categories/shared-category.webp';
        Storage::disk('public')->put($path, 'fake image bytes');

        $category = Category::create([
            'name' => 'Shared category image',
            'image' => $path,
            'is_active' => true,
        ]);

        delete_stored_media('public', $path);
        Storage::disk('public')->assertExists($path);

        $category->update(['image' => null]);
        delete_stored_media('public', $path);
        Storage::disk('public')->assertMissing($path);
    }
}
