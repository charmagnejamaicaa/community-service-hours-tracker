'use strict';

// Tests the workflow endpoints end to end (real Express server, in-memory repo, no database).
const test = require('node:test');
const assert = require('node:assert/strict');
const { createApp } = require('./app');
const { createMemoryStore } = require('./notificationStore');
const { createMemoryWorkflowRepo } = require('./workflowRepoMemory');

const DAY_MS = 24 * 60 * 60 * 1000;
const silentLogger = { info() {}, warn() {}, error() {} };
const isoDay = (offsetDays) => new Date(Date.now() + offsetDays * DAY_MS).toISOString().slice(0, 10);
const YESTERDAY = isoDay(-1);

async function start({ requiredHours = 40, logger = silentLogger } = {}) {
  const workflowRepo = createMemoryWorkflowRepo({
    students: [{ id: 1, requiredHours, deadline: new Date(`${isoDay(30)}T00:00:00Z`) }],
  });
  const app = createApp({ getPool: () => ({}), store: createMemoryStore(), logger, workflowRepo });
  const server = await new Promise((resolve) => {
    const s = app.listen(0, () => resolve(s));
  });
  const base = `http://127.0.0.1:${server.address().port}`;

  async function api(method, path, { role, body } = {}) {
    const headers = { 'Content-Type': 'application/json' };
    if (role) headers['X-Demo-Role'] = role;
    const res = await fetch(base + path, { method, headers, body: body ? JSON.stringify(body) : undefined });
    return { status: res.status, body: await res.json() };
  }

  const ctx = {
    api,
    workflowRepo,
    close: () => new Promise((resolve) => server.close(resolve)),
    async registerActivity() {
      const r = await api('POST', '/api/activities', {
        role: 'student',
        body: { studentId: 1, name: 'Tree Planting', organization: 'Sample Barangay', activityDate: YESTERDAY },
      });
      return r.body.id;
    },
    async submit(activityId, hours = 4, extra = {}) {
      const r = await api('POST', `/api/activities/${activityId}/submissions`, {
        role: 'student',
        body: { hours, serviceDate: YESTERDAY, evidenceNote: 'Signed attendance sheet', ...extra },
      });
      return r.body.id;
    },
    verify: (id) => api('PATCH', `/api/submissions/${id}/verify`, { role: 'supervisor', body: { decision: 'VERIFY' } }),
    approve: (id) => api('PATCH', `/api/submissions/${id}/approve`, { role: 'coordinator' }),
  };
  return ctx;
}

// ---------- Register activity ----------

test('register activity: success returns 201', async () => {
  const t = await start();
  const r = await t.api('POST', '/api/activities', {
    role: 'student',
    body: { studentId: 1, name: 'Tree Planting', organization: 'Sample Barangay', activityDate: YESTERDAY },
  });
  assert.equal(r.status, 201);
  assert.equal(r.body.studentId, 1);
  await t.close();
});

test('register activity: invalid input returns 400 with a list of problems', async () => {
  const t = await start();
  const r = await t.api('POST', '/api/activities', {
    role: 'student',
    body: { studentId: 'x', name: '  ', organization: '', activityDate: '11/03/2026' },
  });
  assert.equal(r.status, 400);
  assert.equal(r.body.error.code, 'VALIDATION_ERROR');
  assert.equal(r.body.error.details.length, 4);
  await t.close();
});

test('register activity: unknown student returns 404', async () => {
  const t = await start();
  const r = await t.api('POST', '/api/activities', {
    role: 'student',
    body: { studentId: 99, name: 'A', organization: 'B', activityDate: YESTERDAY },
  });
  assert.equal(r.status, 404);
  await t.close();
});

test('register activity: other roles and a missing role are forbidden', async () => {
  const t = await start();
  const body = { studentId: 1, name: 'A', organization: 'B', activityDate: YESTERDAY };
  assert.equal((await t.api('POST', '/api/activities', { role: 'supervisor', body })).status, 403);
  assert.equal((await t.api('POST', '/api/activities', { body })).status, 403);
  await t.close();
});

// ---------- Submit hours ----------

test('submit hours: success returns 201 with status SUBMITTED', async () => {
  const t = await start();
  const activityId = await t.registerActivity();
  const r = await t.api('POST', `/api/activities/${activityId}/submissions`, {
    role: 'student',
    body: { hours: 4.5, serviceDate: YESTERDAY, evidenceNote: 'Signed attendance sheet' },
  });
  assert.equal(r.status, 201);
  assert.equal(r.body.status, 'SUBMITTED');
  assert.equal(r.body.hours, 4.5);
  await t.close();
});

test('submit hours: invalid hours, future date, and missing evidence return 400', async () => {
  const t = await start();
  const activityId = await t.registerActivity();
  const post = (body) => t.api('POST', `/api/activities/${activityId}/submissions`, { role: 'student', body });

  assert.equal((await post({ hours: -2, serviceDate: YESTERDAY, evidenceNote: 'x' })).status, 400);
  assert.equal((await post({ hours: 13, serviceDate: YESTERDAY, evidenceNote: 'x' })).status, 400);
  assert.equal((await post({ hours: 2, serviceDate: isoDay(5), evidenceNote: 'x' })).status, 400);
  assert.equal((await post({ hours: 2, serviceDate: isoDay(-60), evidenceNote: 'x' })).status, 400);
  const missing = await post({ hours: 2, serviceDate: YESTERDAY, evidenceNote: '' });
  assert.equal(missing.status, 400);
  assert.ok(missing.body.error.details.some((d) => d.includes('Evidence')));
  await t.close();
});

test('submit hours: more than 12 hours on one day (in total) is refused', async () => {
  const t = await start();
  const activityId = await t.registerActivity();
  await t.submit(activityId, 8);
  const r = await t.api('POST', `/api/activities/${activityId}/submissions`, {
    role: 'student',
    body: { hours: 5, serviceDate: YESTERDAY, evidenceNote: 'x' },
  });
  assert.equal(r.status, 400);
  assert.ok(r.body.error.message.includes('Daily limit'));
  await t.close();
});

test('submit hours: unknown activity returns 404, bad id returns 400, wrong role returns 403', async () => {
  const t = await start();
  const body = { hours: 2, serviceDate: YESTERDAY, evidenceNote: 'x' };
  assert.equal((await t.api('POST', '/api/activities/999/submissions', { role: 'student', body })).status, 404);
  assert.equal((await t.api('POST', '/api/activities/abc/submissions', { role: 'student', body })).status, 400);
  assert.equal((await t.api('POST', '/api/activities/1/submissions', { role: 'coordinator', body })).status, 403);
  await t.close();
});

// ---------- List ----------

test('list: supervisor sees SUBMITTED items; students cannot list; a bad status returns 400', async () => {
  const t = await start();
  const activityId = await t.registerActivity();
  await t.submit(activityId, 2);
  await t.submit(activityId, 3);

  const list = await t.api('GET', '/api/submissions?status=SUBMITTED', { role: 'supervisor' });
  assert.equal(list.status, 200);
  assert.equal(list.body.count, 2);
  assert.equal(list.body.items[0].status, 'SUBMITTED');

  assert.equal((await t.api('GET', '/api/submissions', { role: 'student' })).status, 403);
  assert.equal((await t.api('GET', '/api/submissions?status=NOPE', { role: 'supervisor' })).status, 400);
  await t.close();
});

// ---------- Verify / reject ----------

test('verify: a supervisor verifies; a student cannot; verifying twice returns 409', async () => {
  const t = await start();
  const id = await t.submit(await t.registerActivity());

  const own = await t.api('PATCH', `/api/submissions/${id}/verify`, { role: 'student', body: { decision: 'VERIFY' } });
  assert.equal(own.status, 403);

  const ok = await t.verify(id);
  assert.equal(ok.status, 200);
  assert.equal(ok.body.status, 'VERIFIED');

  const again = await t.verify(id);
  assert.equal(again.status, 409);
  assert.equal(again.body.error.code, 'INVALID_STATE');
  await t.close();
});

test('reject: needs a reason; with a reason the status becomes REJECTED', async () => {
  const t = await start();
  const id = await t.submit(await t.registerActivity());
  const path = `/api/submissions/${id}/verify`;

  const noReason = await t.api('PATCH', path, { role: 'supervisor', body: { decision: 'REJECT' } });
  assert.equal(noReason.status, 400);

  const ok = await t.api('PATCH', path, { role: 'supervisor', body: { decision: 'REJECT', reason: 'Photo is unreadable' } });
  assert.equal(ok.status, 200);
  assert.equal(ok.body.status, 'REJECTED');

  const stored = await t.workflowRepo.getSubmission(id);
  assert.equal(stored.rejectReason, 'Photo is unreadable');
  await t.close();
});

test('verify: bad decision, unknown id, and bad id are rejected', async () => {
  const t = await start();
  const sup = (path, body) => t.api('PATCH', path, { role: 'supervisor', body });
  assert.equal((await sup('/api/submissions/1/verify', { decision: 'MAYBE' })).status, 400);
  assert.equal((await sup('/api/submissions/999/verify', { decision: 'VERIFY' })).status, 404);
  assert.equal((await sup('/api/submissions/abc/verify', { decision: 'VERIFY' })).status, 400);
  await t.close();
});

// ---------- Approve ----------

test('approve: only a VERIFIED submission can be approved, and the remaining hours are recalculated', async () => {
  const t = await start({ requiredHours: 40 });
  const id = await t.submit(await t.registerActivity(), 4.5);

  const early = await t.approve(id); // still SUBMITTED
  assert.equal(early.status, 409);

  await t.verify(id);
  const ok = await t.approve(id);
  assert.equal(ok.status, 200);
  assert.equal(ok.body.status, 'APPROVED');
  assert.equal(ok.body.remainingHours, 35.5);
  assert.equal(ok.body.progressStatus, 'IN_PROGRESS');
  await t.close();
});

test('approve: the student is COMPLETED when approved hours reach the requirement', async () => {
  const t = await start({ requiredHours: 8 });
  const activityId = await t.registerActivity();
  const first = await t.submit(activityId, 5);
  const second = await t.submit(activityId, 3);

  await t.verify(first);
  await t.verify(second);
  const a = await t.approve(first);
  assert.equal(a.body.remainingHours, 3);
  const b = await t.approve(second);
  assert.equal(b.body.remainingHours, 0);
  assert.equal(b.body.progressStatus, 'COMPLETED');
  await t.close();
});

test('approve: students and supervisors cannot approve', async () => {
  const t = await start();
  const id = await t.submit(await t.registerActivity());
  await t.verify(id);
  assert.equal((await t.api('PATCH', `/api/submissions/${id}/approve`, { role: 'student' })).status, 403);
  assert.equal((await t.api('PATCH', `/api/submissions/${id}/approve`, { role: 'supervisor' })).status, 403);
  await t.close();
});

test('approve: two coordinators at the same moment, only one succeeds', async () => {
  const t = await start();
  const id = await t.submit(await t.registerActivity());
  await t.verify(id);

  const [a, b] = await Promise.all([t.approve(id), t.approve(id)]);
  assert.deepEqual([a.status, b.status].sort(), [200, 409]);
  await t.close();
});

test('an approved submission can no longer be changed', async () => {
  const t = await start();
  const id = await t.submit(await t.registerActivity());
  await t.verify(id);
  await t.approve(id);

  const back = await t.api('PATCH', `/api/submissions/${id}/correct`, { role: 'coordinator', body: { reason: 'oops' } });
  assert.equal(back.status, 409);
  const resubmit = await t.api('PATCH', `/api/submissions/${id}/resubmit`, {
    role: 'student',
    body: { hours: 2, serviceDate: YESTERDAY, evidenceNote: 'x' },
  });
  assert.equal(resubmit.status, 409);
  await t.close();
});

// ---------- Correction loop ----------

test('correction loop: coordinator returns it, student resubmits, then it can be approved', async () => {
  const t = await start({ requiredHours: 40 });
  const id = await t.submit(await t.registerActivity(), 3);
  await t.verify(id);

  const noReason = await t.api('PATCH', `/api/submissions/${id}/correct`, { role: 'coordinator', body: {} });
  assert.equal(noReason.status, 400);

  const returned = await t.api('PATCH', `/api/submissions/${id}/correct`, {
    role: 'coordinator',
    body: { reason: 'Hours do not match the attendance sheet' },
  });
  assert.equal(returned.status, 200);
  assert.equal(returned.body.status, 'REJECTED');

  const resub = await t.api('PATCH', `/api/submissions/${id}/resubmit`, {
    role: 'student',
    body: { hours: 3.5, serviceDate: YESTERDAY, evidenceNote: 'Corrected, new photo attached' },
  });
  assert.equal(resub.status, 200);
  assert.equal(resub.body.status, 'SUBMITTED');

  const stored = await t.workflowRepo.getSubmission(id);
  assert.equal(stored.hours, 3.5);
  assert.equal(stored.rejectReason, null);

  await t.verify(id);
  const approved = await t.approve(id);
  assert.equal(approved.body.remainingHours, 36.5);
  await t.close();
});

test('resubmit: validates the corrected values and only works on REJECTED submissions', async () => {
  const t = await start();
  const id = await t.submit(await t.registerActivity(), 3);

  const wrongState = await t.api('PATCH', `/api/submissions/${id}/resubmit`, {
    role: 'student',
    body: { hours: 3, serviceDate: YESTERDAY, evidenceNote: 'x' },
  });
  assert.equal(wrongState.status, 409);

  await t.api('PATCH', `/api/submissions/${id}/verify`, { role: 'supervisor', body: { decision: 'REJECT', reason: 'Fix it' } });
  const invalid = await t.api('PATCH', `/api/submissions/${id}/resubmit`, {
    role: 'student',
    body: { hours: 0, serviceDate: YESTERDAY, evidenceNote: 'x' },
  });
  assert.equal(invalid.status, 400);
  await t.close();
});

test('resubmit does not count the submission itself against the daily limit', async () => {
  const t = await start();
  const activityId = await t.registerActivity();
  const id = await t.submit(activityId, 10);
  await t.api('PATCH', `/api/submissions/${id}/verify`, { role: 'supervisor', body: { decision: 'REJECT', reason: 'Fix it' } });

  const resub = await t.api('PATCH', `/api/submissions/${id}/resubmit`, {
    role: 'student',
    body: { hours: 11, serviceDate: YESTERDAY, evidenceNote: 'Corrected' },
  });
  assert.equal(resub.status, 200);
  await t.close();
});

// ---------- Logging and error safety ----------

test('logs record transitions with IDs only (no evidence text) and never leak errors to the client', async () => {
  const entries = [];
  const logger = {
    info: (event, f) => entries.push({ event, ...f }),
    warn: (event, f) => entries.push({ event, ...f }),
    error: (event, f) => entries.push({ event, ...f }),
  };
  const t = await start({ logger });
  const id = await t.submit(await t.registerActivity(), 4);
  await t.verify(id);
  await t.approve(id);

  const transitions = entries.filter((e) => e.event === 'submission_transition');
  assert.deepEqual(transitions.map((e) => `${e.from}>${e.to}`), ['SUBMITTED>VERIFIED', 'VERIFIED>APPROVED']);
  assert.equal(JSON.stringify(entries).includes('Signed attendance sheet'), false);

  // Force a database failure: the client must get a clean 500 without the internal message.
  t.workflowRepo.getSubmission = async () => {
    throw new Error('connect ECONNREFUSED secret-host');
  };
  const failed = await t.verify(id);
  assert.equal(failed.status, 500);
  assert.equal(JSON.stringify(failed.body).includes('secret-host'), false);
  assert.ok(failed.body.error.referenceId);
  await t.close();
});
