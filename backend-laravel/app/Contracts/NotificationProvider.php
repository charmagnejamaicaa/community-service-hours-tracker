<?php

declare(strict_types=1);

namespace App\Contracts;

interface NotificationProvider
{
    /** Throws App\Services\ProviderException on failure. */
    public function send(array $notification): array;
}
