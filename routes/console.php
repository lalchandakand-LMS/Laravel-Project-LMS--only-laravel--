<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');


Schedule::command('system:backup --keep=14')->dailyAt('02:00')->withoutOverlapping();

Schedule::command('recycle-bin:prune')->dailyAt('02:30')->withoutOverlapping();

// Release stock held by unpaid orders after the 24-hour checkout window.
Schedule::command('orders:expire-unpaid --hours=24')->dailyAt('03:00')->withoutOverlapping();
