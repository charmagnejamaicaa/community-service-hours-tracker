<?php

namespace App\Console\Commands;

use App\Services\JobRunner;
use Illuminate\Console\Command;

class DailyJobCommand extends Command
{
    protected $signature = 'app:daily-job {--trigger=manual : manual or schedule (shows up in the logs)}';

    protected $description = 'Run the daily job: deadline reminders, missing-verification alerts, progress summary';

    public function handle(JobRunner $runner): int
    {
        $outcome = $runner->runOnce((string) $this->option('trigger'));

        if ($outcome['skipped']) {
            $this->warn('The job is already running. Skipped.');

            return self::SUCCESS;
        }

        $this->line(json_encode($outcome['result'], JSON_PRETTY_PRINT));

        return self::SUCCESS;
    }
}
