<?php

use Illuminate\Support\Facades\Schedule;

// Daily automation job: deadline reminders, missing-verification alerts, progress summary.
// Runs at 08:00 Asia/Manila (see config/service_hours.php). Keep it running with:
//   php artisan schedule:work
// withoutOverlapping() stops a second run from starting while one is still going.
Schedule::command('app:daily-job --trigger=schedule')
    ->dailyAt(config('service_hours.job_time', '08:00'))
    ->timezone(config('service_hours.timezone', 'Asia/Manila'))
    ->withoutOverlapping();
