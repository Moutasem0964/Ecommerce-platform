<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Saga cleanup: every minute, release orders stuck in PENDING for more than 5 minutes.
Schedule::command('orders:release-stale')->everyMinute()->withoutOverlapping();
