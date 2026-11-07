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
];
