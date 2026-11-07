<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\EventLogger;
use App\Contracts\JobRepository;
use App\Contracts\NotificationProvider;
use App\Contracts\NotificationStore;
use App\Support\Clock;
use App\Support\JobConfig;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Runs the daily job, but never two at the same time (a manual run while the scheduled run is
 * still going is skipped, not doubled).
 */
final class JobRunner
{
    public function __construct(
        private readonly JobRepository $repo,
        private readonly NotificationStore $store,
        private readonly NotificationProvider $provider,
        private readonly EventLogger $logger,
    ) {
    }

    /** @return array{skipped: bool, result?: array} trigger is "schedule" or "manual" */
    public function runOnce(string $trigger): array
    {
        $lock = Cache::lock('daily-job', 3600);
        if (! $lock->get()) {
            $this->logger->warn('job_skipped_already_running', ['trigger' => $trigger]);

            return ['skipped' => true];
        }

        $startedAt = microtime(true);
        $this->logger->info('job_started', ['trigger' => $trigger]);
        try {
            $job = new DailyJob($this->repo, $this->store, $this->provider, $this->logger, JobConfig::fromLaravel());
            $result = $job->run(Clock::now());
            $this->logger->info('job_finished', ['trigger' => $trigger, 'durationMs' => (int) round((microtime(true) - $startedAt) * 1000)]);

            return ['skipped' => false, 'result' => $result];
        } catch (Throwable $e) {
            $this->logger->error('job_failed', ['trigger' => $trigger, 'reason' => $e::class]);
            throw $e;
        } finally {
            $lock->release();
        }
    }
}
