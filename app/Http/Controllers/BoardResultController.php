<?php

namespace App\Http\Controllers;

use App\Models\Setting;

class BoardResultController extends Controller
{
    public function index()
    {
        return view('board-results.index', [
            'title' => Setting::get('homepage_board_results_title', 'Student Board Results'),
            'subtitle' => Setting::get('homepage_board_results_subtitle', 'Verified achievements from our students.'),
            'body' => Setting::get('homepage_board_results_body', ''),
            'year' => Setting::get('homepage_board_results_year', ''),
        ]);
    }
}
