<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Contracts\NotificationStore;
use App\Support\Iso;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/** MySQL notification store. Needs the `notifications` table from schema_notifications.sql. */
final class DbNotificationStore implements NotificationStore
{
    // Only these fields may be updated.
    private const COLUMNS = [
        'status' => 'status',
        'attempts' => 'attempts',
        'lastError' => 'last_error',
        'nextAttemptAt' => 'next_attempt_at',
        'handledAt' => 'handled_at',
        'message' => 'message',
    ];

    public function createIfNew(array $data): array
    {
        // INSERT IGNORE + UNIQUE key on notif_key = a duplicate is silently skipped.
        $affected = DB::affectingStatement(
            'INSERT IGNORE INTO notifications (notif_key, type, student_id, reference_id, message, status) VALUES (?, ?, ?, ?, ?, ?)',
            [$data['key'], $data['type'], $data['studentId'], $data['referenceId'], $data['message'] ?? null, $data['status'] ?? 'PENDING']
        );
        $row = DB::table('notifications')->where('notif_key', $data['key'])->first();
        if ($row === null) {
            // INSERT IGNORE also hides other problems, such as a student id that does not exist.
            throw new RuntimeException('Notification could not be created (does the student exist?)');
        }

        return ['created' => $affected === 1, 'notification' => $this->toArray($row)];
    }

    public function update(int $id, array $patch): array
    {
        $set = [];
        foreach ($patch as $field => $value) {
            if (isset(self::COLUMNS[$field])) {
                $set[self::COLUMNS[$field]] = $value;
            }
        }
        if ($set !== []) {
            DB::table('notifications')->where('id', $id)->update($set);
        }
        $row = $this->get($id);
        if ($row === null) {
            throw new RuntimeException("Notification $id not found");
        }

        return $row;
    }

    public function get(int $id): ?array
    {
        $row = DB::table('notifications')->where('id', $id)->first();

        return $row === null ? null : $this->toArray($row);
    }

    public function listByStatus(string $status): array
    {
        return DB::table('notifications')->where('status', $status)->orderBy('created_at')->orderBy('id')->get()
            ->map(fn ($row) => $this->toArray($row))->all();
    }

    private function toArray(object $r): array
    {
        return [
            'id' => (int) $r->id,
            'key' => $r->notif_key,
            'type' => $r->type,
            'studentId' => (int) $r->student_id,
            'referenceId' => $r->reference_id,
            'message' => $r->message,
            'status' => $r->status,
            'attempts' => (int) $r->attempts,
            'lastError' => $r->last_error,
            'nextAttemptAt' => Iso::utc($r->next_attempt_at),
            'handledAt' => Iso::utc($r->handled_at),
            'createdAt' => Iso::utc($r->created_at),
        ];
    }
}
