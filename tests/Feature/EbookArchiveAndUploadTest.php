<?php

namespace Tests\Feature;

use App\Models\Ebook;
use App\Models\EbookPurchase;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class EbookArchiveAndUploadTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        Storage::fake('public');
        Storage::fake('local');
    }

    private function admin(): User
    {
        return User::where('email', 'admin@lms.test')->firstOrFail();
    }

    private function student(): User
    {
        return User::where('email', 'student@lms.test')->firstOrFail();
    }

    public function test_admin_can_create_ebook_with_pdf_and_thumbnail_uploads(): void
    {
        $this->actingAs($this->admin())
            ->post(route('admin.ebooks.store'), [
                'title' => 'Uploaded PDF e-book',
                'description' => 'A complete upload test.',
                'price' => 250,
                'pages' => 120,
                'cover' => UploadedFile::fake()->image('cover.jpg'),
                'file' => UploadedFile::fake()->create('book.pdf', 32, 'application/pdf'),
                'is_published' => 1,
            ])
            ->assertRedirect(route('admin.ebooks.index'))
            ->assertSessionHasNoErrors();

        $ebook = Ebook::where('title', 'Uploaded PDF e-book')->firstOrFail();

        $this->assertSame(250.0, (float) $ebook->price);
        $this->assertSame(120, $ebook->pages);
        Storage::disk('public')->assertExists($ebook->cover);
        Storage::disk('local')->assertExists($ebook->file);
    }

    public function test_admin_can_update_ebook_and_replace_files_before_any_purchase(): void
    {
        $oldCover = UploadedFile::fake()->image('old-cover.jpg')->store('ebooks', 'public');
        $oldFile = UploadedFile::fake()->create('old-book.pdf', 12, 'application/pdf')->store('ebook-files', 'local');
        $ebook = Ebook::create([
            'title' => 'Editable e-book',
            'price' => 100,
            'pages' => 50,
            'cover' => $oldCover,
            'file' => $oldFile,
            'is_published' => true,
        ]);

        $this->actingAs($this->admin())
            ->put(route('admin.ebooks.update', $ebook), [
                'title' => 'Updated editable e-book',
                'description' => 'Updated description',
                'price' => 175,
                'pages' => 75,
                'cover' => UploadedFile::fake()->image('new-cover.png'),
                'file' => UploadedFile::fake()->create('new-book.pdf', 20, 'application/pdf'),
                'is_published' => 1,
            ])
            ->assertRedirect(route('admin.ebooks.index'))
            ->assertSessionHasNoErrors();

        $ebook->refresh();
        $this->assertSame('Updated editable e-book', $ebook->title);
        $this->assertSame(175.0, (float) $ebook->price);
        $this->assertSame(75, $ebook->pages);
        $this->assertNotSame($oldCover, $ebook->cover);
        $this->assertNotSame($oldFile, $ebook->file);
        Storage::disk('public')->assertMissing($oldCover);
        Storage::disk('local')->assertMissing($oldFile);
        Storage::disk('public')->assertExists($ebook->cover);
        Storage::disk('local')->assertExists($ebook->file);
    }

    public function test_archiving_and_restoring_preserves_purchase_history_and_uploaded_files(): void
    {
        $cover = UploadedFile::fake()->image('buyer-cover.jpg')->store('ebooks', 'public');
        $file = UploadedFile::fake()->create('buyer-book.pdf', 16, 'application/pdf')->store('ebook-files', 'local');
        $ebook = Ebook::create([
            'title' => 'Purchased e-book archive',
            'price' => 300,
            'pages' => 90,
            'cover' => $cover,
            'file' => $file,
            'is_published' => true,
        ]);
        $student = $this->student();
        $purchase = EbookPurchase::create(['user_id' => $student->id, 'ebook_id' => $ebook->id]);

        $this->actingAs($this->admin())
            ->delete(route('admin.ebooks.destroy', $ebook))
            ->assertRedirect(route('admin.ebooks.index'))
            ->assertSessionHas('success');

        $this->assertSoftDeleted('ebooks', ['id' => $ebook->id]);
        $this->assertDatabaseHas('ebook_purchases', ['id' => $purchase->id, 'ebook_id' => $ebook->id]);
        Storage::disk('public')->assertExists($cover);
        Storage::disk('local')->assertExists($file);

        // Archived items are hidden from the catalogue, but remain in the buyer's library and readable.
        $this->actingAs($student)
            ->get(route('student.ebooks'))
            ->assertOk()
            ->assertSee('Purchased e-book archive')
            ->assertSee('Your access remains active');

        $this->actingAs($student)
            ->get(route('student.ebooks.file', $ebook->slug))
            ->assertOk();

        $this->actingAs($this->admin())
            ->post(route('admin.recycle-bin.restore', ['type' => 'ebooks', 'id' => $ebook->id]))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseHas('ebooks', ['id' => $ebook->id, 'deleted_at' => null]);
        $this->assertDatabaseHas('ebook_purchases', ['id' => $purchase->id, 'ebook_id' => $ebook->id]);
        Storage::disk('public')->assertExists($cover);
        Storage::disk('local')->assertExists($file);
    }

    public function test_pdf_replacement_is_blocked_after_purchase_but_metadata_and_cover_can_be_updated(): void
    {
        $cover = UploadedFile::fake()->image('existing-cover.jpg')->store('ebooks', 'public');
        $file = UploadedFile::fake()->create('purchased.pdf', 10, 'application/pdf')->store('ebook-files', 'local');
        $ebook = Ebook::create([
            'title' => 'Locked purchased content',
            'price' => 200,
            'pages' => 60,
            'cover' => $cover,
            'file' => $file,
            'is_published' => true,
        ]);
        EbookPurchase::create(['user_id' => $this->student()->id, 'ebook_id' => $ebook->id]);

        $this->actingAs($this->admin())
            ->from(route('admin.ebooks.edit', $ebook))
            ->put(route('admin.ebooks.update', $ebook), [
                'title' => 'Updated metadata after sale',
                'description' => 'The purchased file remains unchanged.',
                'price' => 225,
                'pages' => 65,
                'file' => UploadedFile::fake()->create('replacement.pdf', 15, 'application/pdf'),
                'is_published' => 1,
            ])
            ->assertRedirect(route('admin.ebooks.edit', $ebook))
            ->assertSessionHasErrors('file');

        $this->assertSame('Locked purchased content', $ebook->fresh()->title);
        $this->assertSame($file, $ebook->fresh()->file);
        Storage::disk('local')->assertExists($file);

        $this->actingAs($this->admin())
            ->put(route('admin.ebooks.update', $ebook), [
                'title' => 'Updated metadata after sale',
                'description' => 'The purchased file remains unchanged.',
                'price' => 225,
                'pages' => 65,
                'cover' => UploadedFile::fake()->image('updated-cover.png'),
                'is_published' => 1,
            ])
            ->assertRedirect(route('admin.ebooks.index'))
            ->assertSessionHasNoErrors();

        $this->assertSame('Updated metadata after sale', $ebook->fresh()->title);
        $this->assertSame($file, $ebook->fresh()->file);
        Storage::disk('local')->assertExists($file);
    }

    public function test_pdf_url_replacement_is_blocked_when_order_history_exists(): void
    {
        $file = 'https://cdn.example.com/paid-book.pdf';
        $ebook = Ebook::create([
            'title' => 'Order-linked e-book',
            'price' => 450,
            'file' => $file,
            'is_published' => true,
        ]);
        $order = Order::create([
            'user_id' => $this->student()->id,
            'subtotal' => 450,
            'discount' => 0,
            'total' => 450,
            'status' => 'paid',
        ]);
        $order->items()->create([
            'item_type' => 'ebook',
            'item_id' => $ebook->id,
            'title' => $ebook->title,
            'price' => 450,
            'qty' => 1,
        ]);

        $this->actingAs($this->admin())
            ->from(route('admin.ebooks.edit', $ebook))
            ->put(route('admin.ebooks.update', $ebook), [
                'title' => $ebook->title,
                'price' => 450,
                'pages' => 0,
                'file_url' => 'https://cdn.example.com/replacement.pdf',
                'is_published' => 1,
            ])
            ->assertRedirect(route('admin.ebooks.edit', $ebook))
            ->assertSessionHasErrors('file_url');

        $this->assertSame($file, $ebook->fresh()->file);
    }

    public function test_admin_can_save_and_clear_free_and_published_flags(): void
    {
        $this->actingAs($this->admin())
            ->post(route('admin.ebooks.store'), [
                'title' => 'Visibility controlled title',
                'price' => 0,
                'is_free' => 1,
                'is_published' => 1,
            ])
            ->assertRedirect(route('admin.ebooks.index'))
            ->assertSessionHasNoErrors();

        $ebook = Ebook::where('title', 'Visibility controlled title')->firstOrFail();
        $this->assertTrue($ebook->is_free);
        $this->assertTrue($ebook->is_published);

        $this->actingAs($this->admin())
            ->put(route('admin.ebooks.update', $ebook), [
                'title' => $ebook->title,
                'price' => 0,
            ])
            ->assertRedirect(route('admin.ebooks.index'))
            ->assertSessionHasNoErrors();

        $this->assertFalse($ebook->fresh()->is_free);
        $this->assertFalse($ebook->fresh()->is_published);
    }

    public function test_paid_ebook_cannot_be_created_with_a_public_hosted_file_url(): void
    {
        $this->actingAs($this->admin())
            ->from(route('admin.ebooks.create'))
            ->post(route('admin.ebooks.store'), [
                'title' => 'Publicly hosted paid book',
                'price' => 200,
                'file_url' => 'https://cdn.example.com/paid-book.pdf',
                'is_published' => 1,
            ])
            ->assertRedirect(route('admin.ebooks.create'))
            ->assertSessionHasErrors('file_url');

        $this->assertDatabaseMissing('ebooks', ['title' => 'Publicly hosted paid book']);
    }

    public function test_paid_ebook_with_legacy_public_url_does_not_redirect_and_archived_free_title_loses_public_access(): void
    {
        $paid = Ebook::create([
            'title' => 'Legacy hosted paid book',
            'price' => 200,
            'file' => 'https://cdn.example.com/legacy-paid.pdf',
            'is_published' => true,
        ]);
        EbookPurchase::create(['user_id' => $this->student()->id, 'ebook_id' => $paid->id]);

        $this->actingAs($this->student())
            ->get(route('student.ebooks.file', $paid->slug))
            ->assertForbidden();

        $free = Ebook::create([
            'title' => 'Archived free title',
            'price' => 0,
            'is_free' => true,
            'is_published' => true,
            'file' => 'https://cdn.example.com/free-book.pdf',
        ]);

        $this->actingAs($this->student())
            ->get(route('student.ebooks.file', $free->slug))
            ->assertRedirect('https://cdn.example.com/free-book.pdf');

        $free->delete();

        $this->actingAs($this->student())
            ->get(route('student.ebooks.read', $free->slug))
            ->assertForbidden();
    }

    public function test_permanent_delete_is_blocked_for_purchased_ebook_and_allowed_for_unpurchased_ebook(): void
    {
        $cover = UploadedFile::fake()->image('protected-cover.jpg')->store('ebooks', 'public');
        $file = UploadedFile::fake()->create('protected.pdf', 10, 'application/pdf')->store('ebook-files', 'local');
        $protected = Ebook::create([
            'title' => 'Protected purchase record',
            'price' => 100,
            'cover' => $cover,
            'file' => $file,
        ]);
        EbookPurchase::create(['user_id' => $this->student()->id, 'ebook_id' => $protected->id]);

        $this->actingAs($this->admin())
            ->delete(route('admin.ebooks.destroy', $protected))
            ->assertRedirect();

        $this->delete(route('admin.recycle-bin.destroy', ['type' => 'ebooks', 'id' => $protected->id]))
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertSoftDeleted('ebooks', ['id' => $protected->id]);
        $this->assertDatabaseHas('ebook_purchases', ['ebook_id' => $protected->id]);
        Storage::disk('public')->assertExists($cover);
        Storage::disk('local')->assertExists($file);

        $temporaryCover = UploadedFile::fake()->image('temporary-cover.jpg')->store('ebooks', 'public');
        $temporaryFile = UploadedFile::fake()->create('temporary.pdf', 8, 'application/pdf')->store('ebook-files', 'local');
        $unprotected = Ebook::create([
            'title' => 'Unpurchased disposable e-book',
            'price' => 50,
            'cover' => $temporaryCover,
            'file' => $temporaryFile,
        ]);

        $this->delete(route('admin.ebooks.destroy', $unprotected))->assertRedirect();
        $this->delete(route('admin.recycle-bin.destroy', ['type' => 'ebooks', 'id' => $unprotected->id]))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('ebooks', ['id' => $unprotected->id]);
        Storage::disk('public')->assertMissing($temporaryCover);
        Storage::disk('local')->assertMissing($temporaryFile);
    }
}
