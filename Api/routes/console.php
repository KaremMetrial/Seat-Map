<?php

use Illuminate\Support\Facades\Schedule;
use App\Console\Commands\CleanupExpiredLocks;

// Clean up expired seat locks every minute
Schedule::command(CleanupExpiredLocks::class)
    ->everyMinute()
    ->name('cleanup-expired-locks')
    ->withoutOverlapping();

// Display an inspiring quote (demo command)
Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');
