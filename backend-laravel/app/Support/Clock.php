<?php

declare(strict_types=1);

namespace App\Support;

use DateTimeImmutable;
use DateTimeZone;

final class Clock
{
    /** "Now" in the project time zone (Asia/Manila by default), so "today" matches the student's day. */
    public static function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('now', new DateTimeZone((string) config('service_hours.timezone', 'Asia/Manila')));
    }
}
