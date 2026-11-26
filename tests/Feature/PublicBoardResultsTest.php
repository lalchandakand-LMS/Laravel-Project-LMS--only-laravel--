<?php

namespace Tests\Feature;

use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicBoardResultsTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_board_results_page_renders_published_settings(): void
    {
        Setting::set('homepage_board_results_title', 'HSC Board Results 2026');
        Setting::set('homepage_board_results_subtitle', 'Student achievements');
        Setting::set('homepage_board_results_body', 'Student A — GPA 5.00');
        Setting::set('homepage_board_results_year', '2026');

        $this->get(route('board-results.index'))
            ->assertOk()
            ->assertSee('HSC Board Results 2026')
            ->assertSee('Student achievements')
            ->assertSee('Student A — GPA 5.00')
            ->assertSee('Board Results');
    }

    public function test_public_board_results_page_has_clear_empty_state(): void
    {
        $this->get(route('board-results.index'))
            ->assertOk()
            ->assertSee('Results will be published here');
    }
}
