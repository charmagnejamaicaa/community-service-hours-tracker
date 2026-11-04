'use strict';

/**
 * Demo for your teacher: the daily job end to end, WITHOUT a database.
 * Run with:  node demoDailyJob.js      and screenshot the output.
 *
 * What it shows:
 *  - Two deadline reminders. Each one times out on the first attempt, then succeeds
 *    (transient failures that recover on their own).
 *  - Two missing-verification alerts. One is delivered. The other keeps failing, so after
 *    3 attempts it is routed to the manual review queue (permanent failure, human action).
 *  - A progress summary.
 *
 * Waits are shortened so the demo is quick (the real policy is 1 and 5 minutes).
 */

const logger = require('./logger');
const { runDailyJob } = require('./dailyJob');
const { createMemoryStore } = require('./notificationStore');
const { ProviderError } = require('./notificationProvider');

const DAY_MS = 24 * 60 * 60 * 1000;
const inDays = (n) => new Date(Date.now() + n * DAY_MS);

// Fictional data
const students = [
  { id: 1, requiredHours: 40, approvedHours: 22.5, pendingHours: 4.5, deadline: inDays(5) }, // reminder
  { id: 2, requiredHours: 40, approvedHours: 30, pendingHours: 0, deadline: inDays(3) }, // reminder
  { id: 3, requiredHours: 40, approvedHours: 40, pendingHours: 0, deadline: inDays(2) }, // completed: no reminder
  { id: 4, requiredHours: 40, approvedHours: 10, pendingHours: 0, deadline: inDays(-2) }, // overdue: no reminder
  { id: 5, requiredHours: 40, approvedHours: 5, pendingHours: 0, deadline: inDays(90) }, // far away: no reminder
];

const staleSubmissions = [
  { id: 11, studentId: 1, createdAt: inDays(-6) },
  { id: 12, studentId: 2, createdAt: inDays(-5) },
];

const repo = {
  async listStudentsWithHours() {
    return students;
  },
  async listStaleSubmissions() {
    return staleSubmissions;
  },
};

// A scripted fake provider so the demo is repeatable.
const attempts = new Map();
const provider = {
  async send(notification) {
    const count = (attempts.get(notification.referenceId) || 0) + 1;
    attempts.set(notification.referenceId, count);

    if (notification.type === 'DEADLINE_REMINDER' && count === 1) {
      throw new ProviderError('PROVIDER_TIMEOUT', true); // recovers on attempt 2
    }
    if (notification.referenceId === 'submission-12') {
      throw new ProviderError('PROVIDER_UNAVAILABLE', true); // never recovers
    }
    return { messageId: `fake-${notification.id}-${count}` };
  },
};

const config = {
  reminderDaysBeforeDeadline: 7,
  verificationAlertDays: 3,
  maxAttempts: 3,
  delaysMs: [300, 600], // demo only
};

async function main() {
  const store = createMemoryStore();

  console.log('\n=== DAILY JOB DEMO ===\n');
  const result = await runDailyJob({ repo, store, provider, logger, config });

  console.log('\n=== JOB RESULT ===');
  console.table({
    'deadline reminders': result.deadlineReminders,
    'verification alerts': result.verificationAlerts,
  });
  console.log('Progress summary:', result.summary);

  console.log('\n=== MANUAL REVIEW QUEUE (what the coordinator would see) ===');
  const queue = await store.listByStatus('MANUAL_REVIEW');
  console.table(
    queue.map((n) => ({ id: n.id, type: n.type, studentId: n.studentId, attempts: n.attempts, reason: n.lastError }))
  );

  console.log('\n=== SECOND RUN (same day): no duplicates should be sent ===');
  const second = await runDailyJob({ repo, store, provider, logger: { info() {}, warn() {}, error() {} }, config });
  console.table({
    'deadline reminders': second.deadlineReminders,
    'verification alerts': second.verificationAlerts,
  });
}

main().catch((err) => {
  console.error('Demo crashed:', err);
  process.exit(1);
});
