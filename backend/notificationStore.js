'use strict';

/**
 * In-memory store for notifications (used for tests and the demo).
 * The next step replaces this with a MySQL-backed store that has the same methods,
 * so notificationService.js does not need to change.
 *
 * Statuses: PENDING, RETRYING, SENT, MANUAL_REVIEW, HANDLED
 */
function createMemoryStore() {
  const items = new Map();
  let nextId = 1;

  return {
    /** Create a notification unless one with the same key already exists (idempotency). */
    async createIfNew(data) {
      for (const existing of items.values()) {
        if (existing.key === data.key) {
          return { created: false, notification: { ...existing } };
        }
      }
      const notification = {
        id: nextId++,
        attempts: 0,
        lastError: null,
        nextAttemptAt: null,
        createdAt: new Date().toISOString(),
        ...data,
      };
      items.set(notification.id, notification);
      return { created: true, notification: { ...notification } };
    },

    async update(id, patch) {
      const current = items.get(id);
      if (!current) throw new Error(`Notification ${id} not found`);
      const updated = { ...current, ...patch, updatedAt: new Date().toISOString() };
      items.set(id, updated);
      return { ...updated };
    },

    async get(id) {
      const found = items.get(id);
      return found ? { ...found } : null;
    },

    async listByStatus(status) {
      return [...items.values()].filter((n) => n.status === status).map((n) => ({ ...n }));
    },
  };
}

module.exports = { createMemoryStore };
