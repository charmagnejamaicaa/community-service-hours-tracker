'use strict';

// Tests the HTTP endpoints using a real Express server on a random port, with fake database parts.
const test = require('node:test');
const assert = require('node:assert/strict');
const { createApp } = require('./app');
const { createMemoryStore } = require('./notificationStore');
const { createFakeProvider } = require('./notificationProvider');
const { sendNotification } = require('./notificationService');

const silentLogger = { info() {}, warn() {}, error() {} };

// A fake pool that returns prepared answers, in order, for each pool.query call.
function createFakePool(answers) {
  const queue = [...answers];
  return { query: async () => queue.shift() };
}

async function startServer({ pool = createFakePool([]), store = createMemoryStore() } = {}) {
  const app = createApp({ getPool: () => pool, store, logger: silentLogger });
  const server = await new Promise((resolve) => {
    const s = app.listen(0, () => resolve(s));
  });
  const base = `http://127.0.0.1:${server.address().port}`;
  return { base, store, close: () => new Promise((resolve) => server.close(resolve)) };
}

// Put one permanently failed notification into the store using the real retry service.
async function addFailedNotification(store) {
  return sendNotification(
    { type: 'MISSING_VERIFICATION_ALERT', studentId: 2, referenceId: 'submission-4', message: 'SECRET-TEXT' },
    {
      provider: createFakeProvider('always_down'),
      store,
      logger: silentLogger,
      maxAttempts: 3,
      delaysMs: [1, 1],
      sleep: async () => {},
    }
  );
}

test('GET /api/health returns ok', async () => {
  const s = await startServer();
  const res = await fetch(`${s.base}/api/health`);
  assert.equal(res.status, 200);
  assert.equal((await res.json()).status, 'ok');
  await s.close();
});

test('GET /api/notifications/failed is empty at first', async () => {
  const s = await startServer();
  const body = await (await fetch(`${s.base}/api/notifications/failed`)).json();
  assert.equal(body.count, 0);
  assert.deepEqual(body.items, []);
  await s.close();
});

test('failed notifications appear in the queue without the message text', async () => {
  const s = await startServer();
  await addFailedNotification(s.store);

  const body = await (await fetch(`${s.base}/api/notifications/failed`)).json();
  assert.equal(body.count, 1);
  assert.equal(body.items[0].type, 'MISSING_VERIFICATION_ALERT');
  assert.equal(body.items[0].attempts, 3);
  assert.equal(body.items[0].reason, 'PROVIDER_UNAVAILABLE');
  assert.equal(JSON.stringify(body).includes('SECRET-TEXT'), false);
  await s.close();
});

test('PATCH handled removes the item from the queue', async () => {
  const s = await startServer();
  const failed = await addFailedNotification(s.store);

  const res = await fetch(`${s.base}/api/notifications/${failed.id}/handled`, { method: 'PATCH' });
  assert.equal(res.status, 200);
  assert.equal((await res.json()).status, 'HANDLED');

  const body = await (await fetch(`${s.base}/api/notifications/failed`)).json();
  assert.equal(body.count, 0);
  await s.close();
});

test('PATCH handled twice returns 409', async () => {
  const s = await startServer();
  const failed = await addFailedNotification(s.store);
  await fetch(`${s.base}/api/notifications/${failed.id}/handled`, { method: 'PATCH' });

  const res = await fetch(`${s.base}/api/notifications/${failed.id}/handled`, { method: 'PATCH' });
  assert.equal(res.status, 409);
  assert.equal((await res.json()).error.code, 'INVALID_STATE');
  await s.close();
});

test('PATCH handled: unknown id gives 404, bad id gives 400', async () => {
  const s = await startServer();
  const missing = await fetch(`${s.base}/api/notifications/9999/handled`, { method: 'PATCH' });
  assert.equal(missing.status, 404);
  const bad = await fetch(`${s.base}/api/notifications/abc/handled`, { method: 'PATCH' });
  assert.equal(bad.status, 400);
  const body = await bad.json();
  assert.equal(body.error.code, 'VALIDATION_ERROR');
  assert.ok(body.error.referenceId.startsWith('req-'));
  await s.close();
});

test('a notification that was SENT cannot be marked handled', async () => {
  const s = await startServer();
  const sent = await sendNotification(
    { type: 'DEADLINE_REMINDER', studentId: 1, referenceId: 'deadline-1', message: 'hi' },
    { provider: createFakeProvider('ok'), store: s.store, logger: silentLogger }
  );
  const res = await fetch(`${s.base}/api/notifications/${sent.id}/handled`, { method: 'PATCH' });
  assert.equal(res.status, 409);
  await s.close();
});

test('GET progress: 400 for a bad id, 404 for an unknown student', async () => {
  const s = await startServer({ pool: createFakePool([[[]]]) });
  assert.equal((await fetch(`${s.base}/api/students/abc/progress`)).status, 400);
  assert.equal((await fetch(`${s.base}/api/students/99/progress`)).status, 404);
  await s.close();
});

test('GET progress returns remaining hours and a plain YYYY-MM-DD deadline', async () => {
  const pool = createFakePool([
    [[{ id: 1, required_hours: 40, deadline: new Date('2099-12-15T00:00:00Z') }]],
    [[{ approved: 22.5, pending: 4.5 }]],
  ]);
  const s = await startServer({ pool });
  const body = await (await fetch(`${s.base}/api/students/1/progress`)).json();
  assert.equal(body.remainingHours, 17.5);
  assert.equal(body.pendingHours, 4.5);
  assert.equal(body.deadline, '2099-12-15');
  assert.equal(body.status, 'IN_PROGRESS');
  await s.close();
});

test('GET progress: a database error returns 500 with a reference id (no details leaked)', async () => {
  const brokenPool = {
    query: async () => {
      throw new Error('connect ECONNREFUSED secret-host');
    },
  };
  const s = await startServer({ pool: brokenPool });
  const res = await fetch(`${s.base}/api/students/1/progress`);
  assert.equal(res.status, 500);
  const text = JSON.stringify(await res.json());
  assert.equal(text.includes('secret-host'), false);
  assert.ok(text.includes('referenceId'));
  await s.close();
});
