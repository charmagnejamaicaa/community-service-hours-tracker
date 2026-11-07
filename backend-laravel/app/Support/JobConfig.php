<?php

declare(strict_types=1);

namespace App\Support;

final class JobConfig
{
    public const DEFAULT_DELAYS_MS = [60000, 300000]; // 1 minute, then 5 minutes

    /** "500,1000" -> [500, 1000]. Anything invalid falls back to the default policy. */
    public static function parseDelays(mixed $text): array
    {
        if (! is_string($text) || trim($text) === '') {
            return self::DEFAULT_DELAYS_MS;
        }
        $parts = array_map('trim', explode(',', $text));
        foreach ($parts as $p) {
            if ($p === '' || ! ctype_digit($p)) {
                return self::DEFAULT_DELAYS_MS;
            }
        }

        return array_map('intval', $parts);
    }

    /** The settings DailyJob needs, read from config/service_hours.php. */
    public static function fromLaravel(): array
    {
        return [
            'reminderDaysBeforeDeadline' => max(0, (int) config('service_hours.reminder_days_before_deadline')),
            'verificationAlertDays' => max(0, (int) config('service_hours.verification_alert_days')),
            'maxAttempts' => max(1, (int) config('service_hours.retry_max_attempts')),
            'delaysMs' => self::parseDelays(config('service_hours.retry_delays_ms')),
        ];
    }
}
