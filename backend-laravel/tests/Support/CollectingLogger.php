<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Contracts\EventLogger;

/** A logger that remembers events so tests can check them. */
final class CollectingLogger implements EventLogger
{
    /** @var list<array{level:string, event:string, fields:array}> */
    public array $events = [];

    public function info(string $event, array $fields = []): void
    {
        $this->events[] = ['level' => 'INFO', 'event' => $event, 'fields' => $fields];
    }

    public function warn(string $event, array $fields = []): void
    {
        $this->events[] = ['level' => 'WARN', 'event' => $event, 'fields' => $fields];
    }

    public function error(string $event, array $fields = []): void
    {
        $this->events[] = ['level' => 'ERROR', 'event' => $event, 'fields' => $fields];
    }

    public function names(): array
    {
        return array_column($this->events, 'event');
    }
}
