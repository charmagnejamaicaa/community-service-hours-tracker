<?php

// Settings for the Community Service Hours Tracker (read from .env, with safe defaults).
// Changing the retry or reminder policy never needs a code change.
return [
    'timezone' => env('JOB_TIMEZONE', 'Asia/Manila'),
    'job_time' => env('DAILY_JOB_TIME', '08:00'),
    'reminder_days_before_deadline' => (int) env('REMINDER_DAYS_BEFORE_DEADLINE', 7),
    'verification_alert_days' => (int) env('VERIFICATION_ALERT_DAYS', 3),
    'retry_max_attempts' => (int) env('RETRY_MAX_ATTEMPTS', 3),
    // "60000,300000" = wait 1 minute, then 5 minutes
    'retry_delays_ms' => env('RETRY_DELAYS_MS', '60000,300000'),
    // ok | timeout_once | always_down | reject   (fake provider; no real messages are sent)
    'notification_mode' => env('NOTIFICATION_MODE', 'ok'),

    // --- Week 14 security ---
    // How long a sign-in lasts, in minutes. After that the token is rejected and the user must sign in again.
    'token_minutes' => max(1, (int) env('TOKEN_MINUTES', 60)),
    // true = every /api request must use HTTPS (otherwise it is answered with 426).
    'require_https' => (bool) env('REQUIRE_HTTPS', false),
    // Password for the FICTIONAL demo accounts created by `php artisan db:seed`. Keep it in .env only.
    'demo_user_password' => env('DEMO_USER_PASSWORD'),
];
