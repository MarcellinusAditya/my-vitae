<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Retention policy (docs/RETENTION.md): source CVs and export files expire,
// audit rows stay.
Schedule::command('app:prune-files')->dailyAt('02:30')->withoutOverlapping();
