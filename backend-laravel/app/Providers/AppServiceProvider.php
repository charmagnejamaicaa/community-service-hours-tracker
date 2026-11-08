<?php

namespace App\Providers;

use App\Contracts\EventLogger;
use App\Contracts\JobRepository;
use App\Contracts\NotificationProvider;
use App\Contracts\NotificationStore;
use App\Repositories\DbJobRepository;
use App\Repositories\DbNotificationStore;
use App\Services\FakeProvider;
use App\Support\StructuredLog;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(EventLogger::class, fn () => new StructuredLog(storage_path('logs/structured.log')));
        $this->app->singleton(NotificationStore::class, DbNotificationStore::class);
        $this->app->singleton(JobRepository::class, DbJobRepository::class);
        // Fake provider: no real email or SMS is sent. The mode simulates failures (see .env NOTIFICATION_MODE).
        $this->app->singleton(NotificationProvider::class, fn () => new FakeProvider((string) config('service_hours.notification_mode', 'ok')));
    }

    public function boot(): void
    {
        // Sign-in attempts: at most 5 per minute for the same address + email, and 30 per minute per address.
        RateLimiter::for('login', function (Request $request) {
            $email = $request->json('email');
            $email = is_string($email) ? strtolower($email) : '';

            return [
                Limit::perMinute(5)->by('login|' . $request->ip() . '|' . $email),
                Limit::perMinute(30)->by('login-ip|' . $request->ip()),
            ];
        });
    }
}
