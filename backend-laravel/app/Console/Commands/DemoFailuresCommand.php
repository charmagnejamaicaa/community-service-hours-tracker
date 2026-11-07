<?php

namespace App\Console\Commands;

use App\Contracts\EventLogger;
use App\Contracts\NotificationStore;
use App\Services\FakeProvider;
use App\Services\NotificationService;
use App\Support\Clock;
use Illuminate\Console\Command;

/**
 * Week 13 evidence: one recovered transient failure and one permanent failure routed to manual review.
 * Uses short waits so it finishes in a second. Run it with:  php artisan app:demo-failures
 */
class DemoFailuresCommand extends Command
{
    protected $signature = 'app:demo-failures';

    protected $description = 'Demo: a transient failure that recovers, and a permanent failure sent to manual review';

    public function handle(NotificationStore $store, EventLogger $logger): int
    {
        $stamp = date('His');
        $shortDelays = [200, 400];

        $this->info('1) Transient failure that RECOVERS (timeout on attempt 1, success on attempt 2)');
        $recovered = (new NotificationService(new FakeProvider('timeout_once'), $store, $logger, 3, $shortDelays))->send([
            'type' => 'DEADLINE_REMINDER',
            'studentId' => 1,
            'referenceId' => "demo-recovered-$stamp",
            'message' => 'Demo notification.',
        ], Clock::now());
        $this->line("   outcome: {$recovered['outcome']}, attempts: {$recovered['notification']['attempts']}, status: {$recovered['notification']['status']}");

        $this->info('2) PERMANENT failure (provider rejects): goes to manual review at once');
        $permanent = (new NotificationService(new FakeProvider('reject'), $store, $logger, 3, $shortDelays))->send([
            'type' => 'MISSING_VERIFICATION_ALERT',
            'studentId' => 2,
            'referenceId' => "demo-permanent-$stamp",
            'message' => 'Demo notification.',
        ], Clock::now());
        $this->line("   outcome: {$permanent['outcome']}, attempts: {$permanent['notification']['attempts']}, status: {$permanent['notification']['status']}, reason: {$permanent['notification']['lastError']}");

        $this->newLine();
        $this->line('Open GET /api/notifications/failed (or the Review queue in the Flutter app) to see the manual review item.');

        return self::SUCCESS;
    }
}
