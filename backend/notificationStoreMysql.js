'use strict';

/**
 * MySQL version of the notification store.
 * It has the SAME methods as notificationStore.js (createIfNew, update, get, listByStatus),
 * so notificationService.js works with either one without any change.
 *
 * Needs the `notifications` table from schema_notifications.sql.
 */

// Only these fields may be updated (the map also protects against SQL injection through field names).
const UPDATABLE_COLUMNS = {
  status: 'status',
  attempts: 'attempts',
  lastError: 'last_error',
  nextAttemptAt: 'next_attempt_at',
  handledAt: 'handled_at',
  message: 'message',
};

function toIso(value) {
  return value ? new Date(value).toISOString() : null;
}

function rowToNotification(row) {
  return {
    id: row.id,
    key: row.notif_key,
    type: row.type,
    studentId: row.student_id,
    referenceId: row.reference_id,
    message: row.message,
    status: row.status,
    attempts: row.attempts,
    lastError: row.last_error,
    nextAttemptAt: toIso(row.next_attempt_at),
    handledAt: toIso(row.handled_at),
    createdAt: toIso(row.created_at),
  };
}

/** @param {() => import('mysql2/promise').Pool} getPool  function that returns the pool (see db.js) */
function createMysqlStore(getPool) {
  return {
    async createIfNew(data) {
      const pool = getPool();
      // INSERT IGNORE + UNIQUE key on notif_key = a duplicate is silently skipped.
      const [result] = await pool.query(
        `INSERT IGNORE INTO notifications (notif_key, type, student_id, reference_id, message, status)
         VALUES (?, ?, ?, ?, ?, ?)`,
        [data.key, data.type, data.studentId, data.referenceId, data.message ?? null, data.status || 'PENDING']
      );
      const [rows] = await pool.query('SELECT * FROM notifications WHERE notif_key = ?', [data.key]);
      if (rows.length === 0) {
        // INSERT IGNORE also hides other problems, such as a student id that does not exist.
        throw new Error('Notification could not be created (does the student exist?)');
      }
      return { created: result.affectedRows === 1, notification: rowToNotification(rows[0]) };
    },

    async update(id, patch) {
      const pool = getPool();
      const sets = [];
      const values = [];
      for (const [field, value] of Object.entries(patch)) {
        const column = UPDATABLE_COLUMNS[field];
        if (!column) continue; // ignore unknown fields
        sets.push(`${column} = ?`);
        // Date columns accept an ISO string or null; convert them to Date objects for MySQL.
        values.push(column.endsWith('_at') ? toDateOrNull(value) : value);
      }
      if (sets.length > 0) {
        const [result] = await pool.query(`UPDATE notifications SET ${sets.join(', ')} WHERE id = ?`, [...values, id]);
        if (result.affectedRows === 0) throw new Error(`Notification ${id} not found`);
      }
      return this.get(id);
    },

    async get(id) {
      const [rows] = await getPool().query('SELECT * FROM notifications WHERE id = ?', [id]);
      return rows.length ? rowToNotification(rows[0]) : null;
    },

    async listByStatus(status) {
      const [rows] = await getPool().query(
        'SELECT * FROM notifications WHERE status = ? ORDER BY created_at, id',
        [status]
      );
      return rows.map(rowToNotification);
    },
  };
}

function toDateOrNull(value) {
  return value == null ? null : new Date(value);
}

module.exports = { createMysqlStore };
