<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Contracts\NotificationStore;
use RuntimeException;

/** In-memory notification store for tests and demos (same methods as DbNotificationStore). */
final class MemoryNotificationStore implements NotificationStore
{
    /** @var array<int, array> */
    private array $rows = [];
    private int $nextId = 1;

    public function createIfNew(array $data): array
    {
        foreach ($this->rows as $row) {
            if ($row['key'] === $data['key']) {
                return ['created' => false, 'notification' => $row];
            }
        }

        $row = [
            'id' => $this->nextId++,
            'key' => $data['key'],
            'type' => $data['type'],
            'studentId' => $data['studentId'],
            'referenceId' => $data['referenceId'],
            'message' => $data['message'] ?? null,
            'status' => $data['status'] ?? 'PENDING',
            'attempts' => 0,
            'lastError' => null,
            'nextAttemptAt' => null,
            'handledAt' => null,
            'createdAt' => gmdate('Y-m-d\TH:i:s.000\Z'),
        ];
        $this->rows[$row['id']] = $row;

        return ['created' => true, 'notification' => $row];
    }

    public function update(int $id, array $patch): array
    {
        if (! isset($this->rows[$id])) {
            throw new RuntimeException("Notification $id not found");
        }
        $allowed = ['status', 'attempts', 'lastError', 'nextAttemptAt', 'handledAt', 'message'];
        foreach ($patch as $field => $value) {
            if (in_array($field, $allowed, true)) {
                $this->rows[$id][$field] = $value;
            }
        }

        return $this->rows[$id];
    }

    public function get(int $id): ?array
    {
        return $this->rows[$id] ?? null;
    }

    public function listByStatus(string $status): array
    {
        return array_values(array_filter($this->rows, static fn ($r) => $r['status'] === $status));
    }
}
