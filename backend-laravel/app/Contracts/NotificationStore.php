<?php

declare(strict_types=1);

namespace App\Contracts;

interface NotificationStore
{
    /** @return array{created: bool, notification: array} created=false means the same key already existed */
    public function createIfNew(array $data): array;

    public function update(int $id, array $patch): array;

    public function get(int $id): ?array;

    /** @return list<array> */
    public function listByStatus(string $status): array;
}
