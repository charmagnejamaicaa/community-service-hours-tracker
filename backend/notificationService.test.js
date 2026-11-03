'use strict';

// Run with: npm test
const test = require('node:test');
const assert = require('node:assert/strict');
const { sendNotification } = require('./notificationService');
const { createFakeProvider } = require('./notificationProvider');
const { createMemoryStore } = require('./notificationStore');

// A logger that remembers what was logged, so tests can check it.
function createCaptureLogger() {
  const entries = [];
  const add = (level) => (event, fields = {}) => entries.push({ level, event, ...fields });
  return { entries, info: add('INFO'), warn: add('WARN'), error: add('ERROR') };
}

// Fake sleep: does not really wait, but records how long it was asked to wait.
function createFakeSleep() {
  const waits = [];
  const sleep = async (ms) => {
    waits.push(ms);
  };
  return { waits, sleep };
}

const REMINDER = { type: 'DEADLINE_REMINDER', studentId: 1, referenceId: 'deadline-2026-12-15', message: 'Reminder' };

function setup(mode, extra = {}) {
  const provider = createFakeProvider(mode);
  const store = createMemoryStore();
  const logger = createCaptureLogger();
  const { waits, sleep } = createFakeSleep();
  const deps = { provider, store, logger, sleep, maxAttempts: 3, delaysMs: [60000, 300000], ...extra };
  return { provider, store, logger, waits, deps };
}

test('sends on the first attempt when the provider works', async () => {
  const { provider, logger, waits, deps } = setup('ok');
  const result = await sendNotification(REMINDER, deps);

  assert.equal(result.status, 'SENT');
  assert.equal(result.attempts, 1);
  assert.equal(provider.calls, 1);
  assert.deepEqual(waits, []);
  assert.deepEqual(logger.entries.map((e) => e.event), ['notification_sent']);
});

test('DEMO 1: transient failure recovers on retry', async () => {
  const { provider, logger, waits, deps } = setup('timeout_once');
  const result = await sendNotification(REMINDER, deps);

  assert.equal(result.status, 'SENT');
  assert.equal(result.attempts, 2);
  assert.equal(provider.calls, 2);
  assert.deepEqual(waits, [60000]); // waited the first backoff only
  assert.deepEqual(logger.entries.map((e) => e.event), ['notification_attempt_failed', 'notification_sent']);
  assert.equal(logger.entries[0].reason, 'PROVIDER_TIMEOUT');
  assert.equal(logger.entries[0].level, 'WARN');
});

test('DEMO 2: transient failure that never recovers goes to manual review after 3 attempts', async () => {
  const { provider, store, logger, waits, deps } = setup('always_down');
  const result = await sendNotification(REMINDER, deps);

  assert.equal(result.status, 'MANUAL_REVIEW');
  assert.equal(result.attempts, 3);
  assert.equal(result.lastError, 'PROVIDER_UNAVAILABLE');
  assert.equal(provider.calls, 3); // bounded: never more than maxAttempts
  assert.deepEqual(waits, [60000, 300000]); // increasing backoff, no wait after the last attempt
  assert.deepEqual(logger.entries.map((e) => e.event), [
    'notification_attempt_failed',
    'notification_attempt_failed',
    'notification_routed_manual_review',
  ]);
  assert.equal(logger.entries[2].level, 'ERROR');

  const queue = await store.listByStatus('MANUAL_REVIEW');
  assert.equal(queue.length, 1);
});

test('permanent failure is NOT retried and goes straight to manual review', async () => {
  const { provider, logger, waits, deps } = setup('reject');
  const result = await sendNotification(REMINDER, deps);

  assert.equal(result.status, 'MANUAL_REVIEW');
  assert.equal(result.attempts, 1);
  assert.equal(provider.calls, 1);
  assert.deepEqual(waits, []);
  assert.equal(logger.entries[0].permanent, true);
});

test('the same notification requested twice is only sent once', async () => {
  const { provider, logger, deps } = setup('ok');
  const first = await sendNotification(REMINDER, deps);
  const second = await sendNotification(REMINDER, deps);

  assert.equal(provider.calls, 1);
  assert.equal(second.id, first.id);
  assert.equal(logger.entries[1].event, 'notification_duplicate_skipped');
});

test('different notifications are not treated as duplicates', async () => {
  const { provider, deps } = setup('ok');
  await sendNotification(REMINDER, deps);
  await sendNotification({ ...REMINDER, studentId: 2 }, deps);
  assert.equal(provider.calls, 2);
});

test('maxAttempts must be a whole number of 1 or more', async () => {
  const { deps } = setup('ok', { maxAttempts: 0 });
  await assert.rejects(() => sendNotification(REMINDER, deps), RangeError);
});

test('logs never contain the message text (only IDs and codes)', async () => {
  const { logger, deps } = setup('always_down');
  await sendNotification({ ...REMINDER, message: 'SECRET-TEXT' }, deps);
  assert.equal(JSON.stringify(logger.entries).includes('SECRET-TEXT'), false);
});
