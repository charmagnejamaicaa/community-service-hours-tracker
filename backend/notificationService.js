'use strict';

/**
 * Sends a notification with BOUNDED retries (see docs/retry-recovery-policy.md).
 *
 *  - Transient failure (timeout, 503)  -> wait, then retry, up to maxAttempts
 *  - Permanent failure (rejected)      -> stop immediately, route to manual review
 *  - Retries used up                   -> route to manual review
 *  - Same notification requested twice -> skipped (idempotency key), no duplicate sent
 *
 * A failed notification never touches hour records: this module only knows about notifications.
 */

const defaultSleep = (ms) => new Promise((resolve) => setTimeout(resolve, ms));

/** type + studentId + referenceId + date  (one notification of a kind per day) */
function buildKey({ type, studentId, referenceId }, date = new Date()) {
  return [type, studentId, referenceId, date.toISOString().slice(0, 10)].join(':');
}

async function sendNotification({ type, studentId, referenceId, message }, deps) {
  const {
    provider,
    store,
    logger,
    maxAttempts = 3,
    delaysMs = [60 * 1000, 5 * 60 * 1000], // wait 1 min before retry 2, 5 min before retry 3
    sleep = defaultSleep,
    now = () => new Date(),
  } = deps;

  if (!Number.isInteger(maxAttempts) || maxAttempts < 1) {
    throw new RangeError('maxAttempts must be a whole number of 1 or more');
  }

  const key = buildKey({ type, studentId, referenceId }, now());
  const { created, notification } = await store.createIfNew({
    key,
    type,
    studentId,
    referenceId,
    message,
    status: 'PENDING',
  });

  if (!created) {
    logger.info('notification_duplicate_skipped', { notificationId: notification.id, type });
    return notification;
  }

  const id = notification.id;

  for (let attempt = 1; attempt <= maxAttempts; attempt += 1) {
    try {
      await provider.send(notification);
      await store.update(id, { status: 'SENT', attempts: attempt, lastError: null, nextAttemptAt: null });
      logger.info('notification_sent', { notificationId: id, type, attempt });
      return store.get(id);
    } catch (err) {
      const reason = err.reason || 'UNKNOWN_ERROR';
      const permanent = err.transient === false;
      const outOfAttempts = attempt === maxAttempts;

      if (permanent || outOfAttempts) {
        await store.update(id, { status: 'MANUAL_REVIEW', attempts: attempt, lastError: reason, nextAttemptAt: null });
        logger.error('notification_routed_manual_review', {
          notificationId: id,
          type,
          attempts: attempt,
          reason,
          permanent,
        });
        return store.get(id);
      }

      const delay = delaysMs[Math.min(attempt - 1, delaysMs.length - 1)];
      const nextAttemptAt = new Date(now().getTime() + delay).toISOString();
      await store.update(id, { status: 'RETRYING', attempts: attempt, lastError: reason, nextAttemptAt });
      logger.warn('notification_attempt_failed', {
        notificationId: id,
        type,
        attempt,
        maxAttempts,
        reason,
        nextAttemptAt,
      });
      await sleep(delay);
    }
  }

  // Not reachable (the loop always returns), kept as a safety net.
  throw new Error('sendNotification finished without a result');
}

module.exports = { sendNotification, buildKey };
