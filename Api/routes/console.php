<?php

use Illuminate\Support\Facades\Schedule;
use App\Models\ElementLock;

// Clean up expired seat locks every minute
Schedule::call(fn() => ElementLock::cleanup())
    ->everyMinute()
    ->name('cleanup-expired-locks')
    ->withoutOverlapping();

// Display an inspiring quote (demo command)
Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');
