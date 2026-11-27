<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicSitemapTest extends TestCase
{
    use RefreshDatabase;

    public function test_sitemap_contains_public_static_pages_and_catalogue_indexes(): void
    {
        $response = $this->get('/sitemap.xml');

        $response->assertOk()
            ->assertHeader('Content-Type', 'application/xml; charset=UTF-8')
            ->assertSee(route('products.index'), false)
            ->assertSee(route('instructors.index'), false)
            ->assertSee(route('legal.terms'), false)
            ->assertSee(route('legal.privacy'), false)
            ->assertSee(route('legal.refunds'), false)
            ->assertDontSee('/admin', false)
            ->assertDontSee('/checkout', false);
    }

    public function test_sitemap_lists_only_publicly_available_products_and_active_instructors(): void
    {
        $publicProduct = Product::create([
            'title' => 'Public workbook',
            'slug' => 'public-workbook',
            'price' => 250,
            'stock_quantity' => 5,
            'reserved_quantity' => 0,
            'is_published' => true,
        ]);

        Product::create([
            'title' => 'Draft workbook',
            'slug' => 'draft-workbook',
            'price' => 250,
            'stock_quantity' => 5,
            'reserved_quantity' => 0,
            'is_published' => false,
        ]);

        $activeInstructor = User::factory()->create([
            'role' => 'instructor',
            'is_active' => true,
        ]);

        $inactiveInstructor = User::factory()->create([
            'role' => 'instructor',
            'is_active' => false,
        ]);

        $this->get('/sitemap.xml')
            ->assertOk()
            ->assertSee(route('products.show', $publicProduct->slug), false)
            ->assertSee(route('instructors.show', $activeInstructor->id), false)
            ->assertDontSee(route('products.show', 'draft-workbook'), false)
            ->assertDontSee(route('instructors.show', $inactiveInstructor->id), false);
    }
}
