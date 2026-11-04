'use strict';

// Tests the job endpoints, the job runner (no overlapping runs), and the scheduler setup.
const test = require('node:test');
const assert = require('node:assert/strict');
const { createApp } = require('./app');
const { createMemoryStore } = require('./notificationStore');
const { createJobRunner, startScheduler } = require('./scheduler');

const silentLogger = { info() {}, warn() {}, error() {} };

function createCaptureLogger() {
  const entries = [];
  const add = (level) => (event, fields = {}) => entries.push({ level, event, ...fields });
  return { entries, info: add('INFO'), warn: add('WARN'), error: add('ERROR') };
}

async function startServer({ repo, jobRunner }) {
  const app = createApp({
    getPool: () => ({}),
    store: createMemoryStore(),
    logger: silentLogger,
    repo,
    jobRunner,
  });
  const server = await new Promise((resolve) => {
    const s = app.listen(0, () => resolve(s));
  });
  return {
    base: `http://127.0.0.1:${server.address().port}`,
    close: () => new Promise((resolve) => server.close(resolve)),
  };
}

const waitFor = async (condition) => {
  for (let i = 0; i < 200; i += 1) {
    if (condition()) return;
    await new Promise((resolve) => setTimeout(resolve, 5));
  }
  throw new Error('condition was not met in time');
};

test('POST /api/jobs/run without the coordinator role is forbidden', async () => {
  const runner = createJobRunner({ run: async () => ({ ok: true }), logger: silentLogger });
  const s = await startServer({ repo: {}, jobRunner: runner });

  const none = await fetch(`${s.base}/api/jobs/run`, { method: 'POST' });
  assert.equal(none.status, 403);
  assert.equal((await none.json()).error.code, 'FORBIDDEN');

  const student = await fetch(`${s.base}/api/jobs/run`, { method: 'POST', headers: { 'X-Demo-Role': 'student' } });
  assert.equal(student.status, 403);
  await s.close();
});

test('POST /api/jobs/run as coordinator runs the job and returns its result', async () => {
  const runner = createJobRunner({ run: async () => ({ deadlineReminders: { sent: 2 } }), logger: silentLogger });
  const s = await startServer({ repo: {}, jobRunner: runner });

  const res = await fetch(`${s.base}/api/jobs/run`, { method: 'POST', headers: { 'X-Demo-Role': 'coordinator' } });
  assert.equal(res.status, 200);
  assert.equal((await res.json()).deadlineReminders.sent, 2);
  await s.close();
});

test('POST /api/jobs/run while the job is already running returns 409', async () => {
  let release;
  const gate = new Promise((resolve) => {
    release = resolve;
  });
  const runner = createJobRunner({ run: () => gate.then(() => ({ done: true })), logger: silentLogger });
  const s = await startServer({ repo: {}, jobRunner: runner });
  const headers = { 'X-Demo-Role': 'coordinator' };

  const first = fetch(`${s.base}/api/jobs/run`, { method: 'POST', headers });
  await waitFor(() => runner.isRunning);

  const second = await fetch(`${s.base}/api/jobs/run`, { method: 'POST', headers });
  assert.equal(second.status, 409);
  assert.equal((await second.json()).error.code, 'JOB_RUNNING');

  release();
  assert.equal((await first).status, 200);
  await s.close();
});

test('POST /api/jobs/run returns 500 with a reference ID when the job fails', async () => {
  const runner = createJobRunner({
    run: async () => {
      throw new Error('secret-detail');
    },
    logger: silentLogger,
  });
  const s = await startServer({ repo: {}, jobRunner: runner });

  const res = await fetch(`${s.base}/api/jobs/run`, { method: 'POST', headers: { 'X-Demo-Role': 'coordinator' } });
  assert.equal(res.status, 500);
  const text = JSON.stringify(await res.json());
  assert.equal(text.includes('secret-detail'), false);
  assert.ok(text.includes('referenceId'));
  await s.close();
});

test('GET /api/summaries/progress returns totals and per-student rows', async () => {
  const repo = {
    async listStudentsWithHours() {
      return [
        { id: 1, requiredHours: 40, approvedHours: 40, pendingHours: 0, deadline: new Date('2099-01-01T00:00:00Z') },
        { id: 2, requiredHours: 40, approvedHours: 10, pendingHours: 0, deadline: new Date('2099-01-01T00:00:00Z') },
      ];
    },
  };
  const runner = createJobRunner({ run: async () => ({}), logger: silentLogger });
  const s = await startServer({ repo, jobRunner: runner });

  const body = await (await fetch(`${s.base}/api/summaries/progress`)).json();
  assert.equal(body.totals.students, 2);
  assert.equal(body.totals.completed, 1);
  assert.equal(body.totals.inProgress, 1);
  assert.equal(body.students[0].deadline, '2099-01-01');
  await s.close();
});

test('job runner: a failed run is logged, rethrown, and does not block the next run', async () => {
  const logger = createCaptureLogger();
  let calls = 0;
  const runner = createJobRunner({
    run: async () => {
      calls += 1;
      if (calls === 1) throw new Error('boom');
      return { ok: true };
    },
    logger,
  });

  await assert.rejects(() => runner.runOnce('manual'), /boom/);
  assert.equal(runner.isRunning, false);
  const second = await runner.runOnce('manual');
  assert.equal(second.skipped, false);
  assert.deepEqual(
    logger.entries.map((e) => e.event),
    ['job_started', 'job_failed', 'job_started', 'job_finished']
  );
});

test('job runner: a second call while running is skipped and logged', async () => {
  const logger = createCaptureLogger();
  let release;
  const gate = new Promise((resolve) => {
    release = resolve;
  });
  const runner = createJobRunner({ run: () => gate.then(() => ({})), logger });

  const first = runner.runOnce('schedule');
  const second = await runner.runOnce('manual');
  assert.equal(second.skipped, true);
  assert.ok(logger.entries.some((e) => e.event === 'job_skipped_already_running'));

  release();
  await first;
});

test('scheduler registers the cron expression and timezone', () => {
  const registered = [];
  const cron = {
    validate: () => true,
    schedule: (expression, fn, options) => registered.push({ expression, fn, options }),
  };
  const runner = createJobRunner({ run: async () => ({}), logger: silentLogger });

  startScheduler({ cron, runner, expression: '0 8 * * *', timezone: 'Asia/Manila', logger: silentLogger });
  assert.equal(registered.length, 1);
  assert.equal(registered[0].expression, '0 8 * * *');
  assert.equal(registered[0].options.timezone, 'Asia/Manila');
});

test('scheduler rejects an invalid cron expression', () => {
  const cron = { validate: () => false, schedule: () => {} };
  const runner = createJobRunner({ run: async () => ({}), logger: silentLogger });
  assert.throws(
    () => startScheduler({ cron, runner, expression: 'not cron', timezone: 'Asia/Manila', logger: silentLogger }),
    /Invalid cron expression/
  );
});

test('a scheduled run that fails does not crash the server', async () => {
  let scheduled;
  const cron = { validate: () => true, schedule: (expression, fn) => (scheduled = fn) };
  const runner = createJobRunner({
    run: async () => {
      throw new Error('boom');
    },
    logger: silentLogger,
  });
  startScheduler({ cron, runner, expression: '0 8 * * *', timezone: 'Asia/Manila', logger: silentLogger });

  scheduled(); // must not throw
  await new Promise((resolve) => setTimeout(resolve, 20)); // an unhandled rejection would fail the test run
  assert.equal(runner.isRunning, false);
});
