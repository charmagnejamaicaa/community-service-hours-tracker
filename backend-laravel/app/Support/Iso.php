<?php

declare(strict_types=1);

namespace App\Support;

use DateTimeImmutable;
use DateTimeZone;

final class Iso
{
    /** "2026-11-04 06:46:25" -> "2026-11-04T06:46:25.000Z" (database times are treated as UTC) */
    public static function utc(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (new DateTimeImmutable($value, new DateTimeZone('UTC')))->format('Y-m-d\TH:i:s.v\Z');
    }
}
