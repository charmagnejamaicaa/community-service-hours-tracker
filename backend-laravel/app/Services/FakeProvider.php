<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\NotificationProvider;
use InvalidArgumentException;

/**
 * A FAKE notification provider used to simulate real-world failures.
 * (No real email or SMS is sent. Real providers are out of scope for this project.)
 *
 * Modes:
 *   ok            always succeeds
 *   timeout_once  fails with a timeout on the 1st call, then succeeds (transient failure that recovers)
 *   always_down   always fails with 503/timeout                       (transient failure that never recovers)
 *   reject        always rejects, e.g. invalid recipient               (permanent failure)
 */
final class FakeProvider implements NotificationProvider
{
    private int $calls = 0;

    public function __construct(private readonly string $mode = 'ok')
    {
        if (! in_array($mode, ['ok', 'timeout_once', 'always_down', 'reject'], true)) {
            throw new InvalidArgumentException("Unknown fake provider mode: $mode");
        }
    }

    public function calls(): int
    {
        return $this->calls;
    }

    public function send(array $notification): array
    {
        $this->calls++;

        return match ($this->mode) {
            'ok' => ['messageId' => 'fake-' . $this->calls],
            'timeout_once' => $this->calls === 1
                ? throw new ProviderException('PROVIDER_TIMEOUT', true)
                : ['messageId' => 'fake-' . $this->calls],
            'always_down' => throw new ProviderException('PROVIDER_UNAVAILABLE', true),
            'reject' => throw new ProviderException('PROVIDER_REJECTED', false),
        };
    }
}
