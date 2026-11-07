<?php

declare(strict_types=1);

namespace App\Contracts;

/** Structured logger: one event name plus a few fields (IDs and codes only, no personal data). */
interface EventLogger
{
    public function info(string $event, array $fields = []): void;

    public function warn(string $event, array $fields = []): void;

    public function error(string $event, array $fields = []): void;
}
