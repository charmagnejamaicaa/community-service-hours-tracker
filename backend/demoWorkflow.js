'use strict';

/**
 * Demo for your teacher: the whole workflow, WITHOUT a database.
 * Run with:  node demoWorkflow.js     and screenshot the table.
 *
 * It starts the real API in memory and walks through:
 *   - the normal path (register -> submit -> verify -> approve -> remaining hours updated)
 *   - invalid input (bad hours, future date)
 *   - unauthorized actions (wrong role)
 *   - an invalid state change (verify twice)
 *   - the correction loop (reject / return, then resubmit)
 *   - the daily 12-hour limit
 */

const { createApp } = require('./app');
const { createMemoryStore } = require('./notificationStore');
const { createMemoryWorkflowRepo } = require('./workflowRepoMemory');

const DAY_MS = 24 * 60 * 60 * 1000;
const isoDay = (offsetDays) => new Date(Date.now() + offsetDays * DAY_MS).toISOString().slice(0, 10);
const YESTERDAY = isoDay(-1);
const silentLogger = { info() {}, warn() {}, error() {} };

async function main() {
  const workflowRepo = createMemoryWorkflowRepo({
    students: [{ id: 1, requiredHours: 40, deadline: new Date(`${isoDay(30)}T00:00:00Z`) }],
  });
  const app = createApp({ getPool: () => ({}), store: createMemoryStore(), logger: silentLogger, workflowRepo });
  const server = await new Promise((resolve) => {
    const s = app.listen(0, () => resolve(s));
  });
  const base = `http://127.0.0.1:${server.address().port}`;

  const rows = [];
  async function call(label, role, method, path, body) {
    const headers = { 'Content-Type': 'application/json' };
    if (role) headers['X-Demo-Role'] = role;
    const res = await fetch(base + path, { method, headers, body: body ? JSON.stringify(body) : undefined });
    const json = await res.json();
    let result;
    if (json.error) {
      result = `${json.error.code}: ${json.error.message}`;
    } else {
      result = ['id', 'count', 'status', 'remainingHours', 'progressStatus']
        .filter((k) => k in json)
        .map((k) => `${k}=${json[k]}`)
        .join(' ');
    }
    rows.push({ '#': rows.length + 1, role: role || '(none)', action: label, http: res.status, result: result.slice(0, 70) });
    return json;
  }

  const hours = (h, note = 'Signed attendance sheet') => ({ hours: h, serviceDate: YESTERDAY, evidenceNote: note });

  // Normal path
  const activity = await call('register activity', 'student', 'POST', '/api/activities', {
    studentId: 1, name: 'Tree Planting', organization: 'Sample Barangay', activityDate: YESTERDAY,
  });
  const first = await call('submit 4.5 hours', 'student', 'POST', `/api/activities/${activity.id}/submissions`, hours(4.5));
  await call('list waiting submissions', 'supervisor', 'GET', '/api/submissions?status=SUBMITTED');

  // Invalid input
  await call('submit -2 hours (invalid)', 'student', 'POST', `/api/activities/${activity.id}/submissions`, hours(-2));
  await call('submit 13 hours (invalid)', 'student', 'POST', `/api/activities/${activity.id}/submissions`, hours(13));
  await call('submit future date (invalid)', 'student', 'POST', `/api/activities/${activity.id}/submissions`, {
    hours: 2, serviceDate: isoDay(5), evidenceNote: 'x',
  });

  // Unauthorized and invalid state
  await call('student tries to verify (unauthorized)', 'student', 'PATCH', `/api/submissions/${first.id}/verify`, { decision: 'VERIFY' });
  await call('supervisor verifies', 'supervisor', 'PATCH', `/api/submissions/${first.id}/verify`, { decision: 'VERIFY' });
  await call('supervisor verifies again (invalid state)', 'supervisor', 'PATCH', `/api/submissions/${first.id}/verify`, { decision: 'VERIFY' });
  await call('coordinator approves', 'coordinator', 'PATCH', `/api/submissions/${first.id}/approve`);

  // Correction loop
  const second = await call('submit 3 hours', 'student', 'POST', `/api/activities/${activity.id}/submissions`, hours(3));
  await call('reject without reason (invalid)', 'supervisor', 'PATCH', `/api/submissions/${second.id}/verify`, { decision: 'REJECT' });
  await call('reject with reason', 'supervisor', 'PATCH', `/api/submissions/${second.id}/verify`, { decision: 'REJECT', reason: 'Photo is unreadable' });
  await call('student resubmits corrected hours', 'student', 'PATCH', `/api/submissions/${second.id}/resubmit`, hours(3.5, 'New clear photo'));
  await call('supervisor verifies', 'supervisor', 'PATCH', `/api/submissions/${second.id}/verify`, { decision: 'VERIFY' });
  await call('coordinator returns for correction', 'coordinator', 'PATCH', `/api/submissions/${second.id}/correct`, { reason: 'Hours differ from the sheet' });
  await call('student resubmits again', 'student', 'PATCH', `/api/submissions/${second.id}/resubmit`, hours(3));
  await call('supervisor verifies', 'supervisor', 'PATCH', `/api/submissions/${second.id}/verify`, { decision: 'VERIFY' });
  await call('coordinator approves', 'coordinator', 'PATCH', `/api/submissions/${second.id}/approve`);

  // Daily limit
  await call('submit 9 more hours on the same day (limit)', 'student', 'POST', `/api/activities/${activity.id}/submissions`, hours(9));

  console.log('\n=== WORKFLOW DEMO ===\n');
  console.table(rows);

  const finalHours = await workflowRepo.getStudentHours(1);
  console.log(`Final: ${finalHours.approvedHours} approved hours, ${40 - finalHours.approvedHours} remaining of 40.\n`);

  await new Promise((resolve) => server.close(resolve));
}

main().catch((err) => {
  console.error('Demo crashed:', err);
  process.exit(1);
});
