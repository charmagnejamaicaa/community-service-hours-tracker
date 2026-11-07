<?php

declare(strict_types=1);

namespace App\Support;

use App\Contracts\EventLogger;

/**
 * Structured JSON logger: one line per event, written to storage/logs/structured.log
 * and to the terminal. Do NOT log passwords, tokens, or unnecessary personal data.
 * Prefer IDs and codes over names and messages.
 */
final class StructuredLog implements EventLogger
{
    public function __construct(private readonly ?string $file = null)
    {
    }

    public function info(string $event, array $fields = []): void
    {
        $this->write('INFO', $event, $fields);
    }

    public function warn(string $event, array $fields = []): void
    {
        $this->write('WARN', $event, $fields);
    }

    public function error(string $event, array $fields = []): void
    {
        $this->write('ERROR', $event, $fields);
    }

    private function write(string $level, string $event, array $fields): void
    {
        $line = json_encode(
            ['ts' => gmdate('Y-m-d\TH:i:s\Z'), 'level' => $level, 'event' => $event] + $fields,
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        ) . PHP_EOL;

        if ($this->file !== null) {
            @file_put_contents($this->file, $line, FILE_APPEND | LOCK_EX);
        }
        if (defined('STDERR')) {
            @fwrite(STDERR, $line);
        }
    }
}
