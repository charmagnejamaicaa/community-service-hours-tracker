'use strict';

// Run with: npm test
const test = require('node:test');
const assert = require('node:assert/strict');
const { runDailyJob } = require('./dailyJob');
const { loadJobConfig, parseDelays } = require('./jobConfig');
const { buildProgressSummary } = require('./progressSummary');
const { createMemoryStore } = require('./notificationStore');
const { createFakeProvider, ProviderError } = require('./notificationProvider');

const NOW = new Date('2026-11-04T00:00:00Z');
const DAY_MS = 24 * 60 * 60 * 1000;
const inDays = (n) => new Date(NOW.getTime() + n * DAY_MS);

const silentLogger = { info() {}, warn() {}, error() {} };
const noSleep = async () => {};

const STUDENTS = [
  { id: 1, requiredHours: 40, approvedHours: 22.5, pendingHours: 4.5, deadline: inDays(10) }, // reminder
  { id: 2, requiredHours: 40, approvedHours: 10, pendingHours: 0, deadline: inDays(-3) }, // overdue
  { id: 3, requiredHours: 40, approvedHours: 40, pendingHours: 0, deadline: inDays(5) }, // completed
  { id: 4, requiredHours: 40, approvedHours: 5, pendingHours: 0, deadline: inDays(60) }, // too far away
  { id: 5, requiredHours: 40, approvedHours: 5, pendingHours: 0, deadline: null }, // no deadline
];

const STALE = [
  { id: 11, studentId: 1, createdAt: inDays(-6) },
  { id: 12, studentId: 4, createdAt: inDays(-5) },
];

function createFakeRepo() {
  const repo = {
    cutoffs: [],
    async listStudentsWithHours() {
      return STUDENTS;
    },
    async listStaleSubmissions(cutoff) {
      repo.cutoffs.push(cutoff);
      return STALE;
    },
  };
  return repo;
}

const CONFIG = {
  reminderDaysBeforeDeadline: 14,
  verificationAlertDays: 3,
  maxAttempts: 3,
  delaysMs: [1, 1],
};

function run(overrides = {}) {
  const store = overrides.store || createMemoryStore();
  const repo = overrides.repo || createFakeRepo();
  const provider = overrides.provider || createFakeProvider('ok');
  return runDailyJob({
    repo,
    store,
    provider,
    logger: silentLogger,
    config: CONFIG,
    now: () => NOW,
    sleep: noSleep,
  }).then((result) => ({ result, store, repo, provider }));
}

test('deadline reminder goes only to in-progress students whose deadline is within the window', async () => {
  const { result, store } = await run();
  assert.equal(result.deadlineReminders.considered, 1);
  assert.equal(result.deadlineReminders.sent, 1);

  const all = await store.listByStatus('SENT');
  const reminders = all.filter((n) => n.type === 'DEADLINE_REMINDER');
  assert.equal(reminders.length, 1);
  assert.equal(reminders[0].studentId, 1);
});

test('missing-verification alerts use the cutoff of now minus the configured days', async () => {
  const { result, repo } = await run();
  assert.equal(result.verificationAlerts.considered, 2);
  assert.equal(result.verificationAlerts.sent, 2);
  assert.equal(repo.cutoffs[0].getTime(), NOW.getTime() - 3 * DAY_MS);
});

test('running the job twice on the same day sends no duplicates', async () => {
  const store = createMemoryStore();
  const provider = createFakeProvider('ok');
  const first = await run({ store, provider });
  const callsAfterFirst = provider.calls;
  const second = await run({ store, provider });

  assert.equal(first.result.deadlineReminders.sent, 1);
  assert.equal(second.result.deadlineReminders.sent, 0);
  assert.equal(second.result.deadlineReminders.duplicates, 1);
  assert.equal(second.result.verificationAlerts.duplicates, 2);
  assert.equal(provider.calls, callsAfterFirst); // nothing new was sent
});

test('a transient failure recovers: the notification is still SENT', async () => {
  const provider = createFakeProvider('timeout_once');
  const { result, store } = await run({ provider });

  assert.equal(result.deadlineReminders.sent, 1);
  assert.equal(result.deadlineReminders.manualReview, 0);
  const sent = (await store.listByStatus('SENT')).find((n) => n.type === 'DEADLINE_REMINDER');
  assert.equal(sent.attempts, 2);
});

test('a failure that never recovers goes to manual review; other items still run', async () => {
  const provider = {
    async send(notification) {
      if (notification.referenceId === 'submission-12') throw new ProviderError('PROVIDER_UNAVAILABLE', true);
      return { messageId: 'ok' };
    },
  };
  const { result, store } = await run({ provider });

  assert.equal(result.deadlineReminders.sent, 1);
  assert.equal(result.verificationAlerts.sent, 1);
  assert.equal(result.verificationAlerts.manualReview, 1);

  const queue = await store.listByStatus('MANUAL_REVIEW');
  assert.equal(queue.length, 1);
  assert.equal(queue[0].referenceId, 'submission-12');
  assert.equal(queue[0].attempts, 3);
});

test('one item that crashes does not stop the rest of the job', async () => {
  const realStore = createMemoryStore();
  const flakyStore = {
    ...realStore,
    async createIfNew(data) {
      if (data.referenceId === 'submission-11') throw new Error('database down');
      return realStore.createIfNew(data);
    },
  };
  const { result } = await run({ store: flakyStore });

  assert.equal(result.verificationAlerts.errors, 1);
  assert.equal(result.verificationAlerts.sent, 1); // submission-12 still went out
  assert.equal(result.deadlineReminders.sent, 1);
});

test('the result includes the progress summary totals', async () => {
  const { result } = await run();
  assert.equal(result.summary.students, 5);
  assert.equal(result.summary.completed, 1);
  assert.equal(result.summary.overdue, 1);
  assert.equal(result.summary.inProgress, 3);
});

test('buildProgressSummary returns IDs and numbers only (no names)', () => {
  const summary = buildProgressSummary(STUDENTS, NOW);
  assert.equal(summary.students.length, 5);
  assert.equal(summary.totals.approvedHours, 82.5);
  assert.equal(summary.students[0].deadline, '2026-11-14');
  assert.equal(JSON.stringify(summary).includes('name'), false);
});

test('loadJobConfig uses safe defaults', () => {
  const config = loadJobConfig({});
  assert.equal(config.reminderDaysBeforeDeadline, 7);
  assert.equal(config.verificationAlertDays, 3);
  assert.equal(config.maxAttempts, 3);
  assert.deepEqual(config.delaysMs, [60000, 300000]);
  assert.equal(config.providerMode, 'ok');
  assert.equal(config.cronExpression, '0 8 * * *');
  assert.equal(config.timezone, 'Asia/Manila');
});

test('loadJobConfig reads values and ignores invalid ones', () => {
  const good = loadJobConfig({
    REMINDER_DAYS_BEFORE_DEADLINE: '60',
    VERIFICATION_ALERT_DAYS: '0',
    RETRY_MAX_ATTEMPTS: '5',
    RETRY_DELAYS_MS: '500,1000',
  });
  assert.equal(good.reminderDaysBeforeDeadline, 60);
  assert.equal(good.verificationAlertDays, 0);
  assert.equal(good.maxAttempts, 5);
  assert.deepEqual(good.delaysMs, [500, 1000]);

  const bad = loadJobConfig({ REMINDER_DAYS_BEFORE_DEADLINE: 'abc', RETRY_MAX_ATTEMPTS: '0', RETRY_DELAYS_MS: 'x,y' });
  assert.equal(bad.reminderDaysBeforeDeadline, 7);
  assert.equal(bad.maxAttempts, 1); // at least one attempt
  assert.deepEqual(bad.delaysMs, [60000, 300000]);
  assert.deepEqual(parseDelays('-5'), [60000, 300000]);
});
