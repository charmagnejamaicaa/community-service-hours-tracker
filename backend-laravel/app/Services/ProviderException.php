<?php

declare(strict_types=1);

namespace App\Services;

use RuntimeException;

/**
 * A notification provider failure.
 *   reason:    short code for logs (for example PROVIDER_TIMEOUT)
 *   transient: true  = temporary problem, worth retrying
 *              false = permanent problem, retrying will not help
 */
final class ProviderException extends RuntimeException
{
    public function __construct(public readonly string $reason, public readonly bool $transient)
    {
        parent::__construct($reason);
    }
}
