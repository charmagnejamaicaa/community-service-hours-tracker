'use strict';

// Tests the workflow rules (pure code, no server needed).
const test = require('node:test');
const assert = require('node:assert/strict');
const { nextStatus, TRANSITIONS, ROLES, STATUSES } = require('./workflow');
const { createMemoryWorkflowRepo } = require('./workflowRepoMemory');

test('the normal path: SUBMITTED -> VERIFIED -> APPROVED', () => {
  assert.deepEqual(nextStatus('verify', 'SUBMITTED'), { ok: true, to: 'VERIFIED' });
  assert.deepEqual(nextStatus('approve', 'VERIFIED'), { ok: true, to: 'APPROVED' });
});

test('the correction loop: reject or return, then resubmit', () => {
  assert.deepEqual(nextStatus('reject', 'SUBMITTED'), { ok: true, to: 'REJECTED' });
  assert.deepEqual(nextStatus('return', 'VERIFIED'), { ok: true, to: 'REJECTED' });
  assert.deepEqual(nextStatus('resubmit', 'REJECTED'), { ok: true, to: 'SUBMITTED' });
});

test('every action is refused from every status it does not allow', () => {
  for (const [action, rule] of Object.entries(TRANSITIONS)) {
    for (const status of STATUSES) {
      const expected = rule.from.includes(status);
      assert.equal(nextStatus(action, status).ok, expected, `${action} from ${status}`);
    }
  }
});

test('APPROVED is final: nothing can change an approved submission', () => {
  for (const action of Object.keys(TRANSITIONS)) {
    assert.equal(nextStatus(action, 'APPROVED').ok, false, action);
  }
});

test('an unknown action is an error', () => {
  assert.throws(() => nextStatus('delete', 'SUBMITTED'), /Unknown action/);
});

test('each action belongs to the right role', () => {
  assert.equal(TRANSITIONS.verify.role, ROLES.SUPERVISOR);
  assert.equal(TRANSITIONS.reject.role, ROLES.SUPERVISOR);
  assert.equal(TRANSITIONS.approve.role, ROLES.COORDINATOR);
  assert.equal(TRANSITIONS.return.role, ROLES.COORDINATOR);
  assert.equal(TRANSITIONS.resubmit.role, ROLES.STUDENT);
});

test('memory repo: transition only works from the expected status', async () => {
  const repo = createMemoryWorkflowRepo({ students: [{ id: 1, requiredHours: 40 }] });
  const activity = await repo.createActivity({ studentId: 1, name: 'A', organization: 'O', activityDate: '2026-11-01' });
  const sub = await repo.createSubmission({ activityId: activity.id, hours: 2, serviceDate: '2026-11-01', evidenceNote: 'x' });

  assert.equal(await repo.transition(sub.id, 'VERIFIED', { status: 'APPROVED' }), false); // wrong "from"
  assert.equal(await repo.transition(sub.id, 'SUBMITTED', { status: 'VERIFIED' }), true);
  assert.equal(await repo.transition(sub.id, 'SUBMITTED', { status: 'VERIFIED' }), false); // already changed
  assert.equal((await repo.getSubmission(sub.id)).status, 'VERIFIED');
});

test('memory repo: daily hours ignore rejected submissions and the excluded one', async () => {
  const repo = createMemoryWorkflowRepo({ students: [{ id: 1, requiredHours: 40 }] });
  const activity = await repo.createActivity({ studentId: 1, name: 'A', organization: 'O', activityDate: '2026-11-01' });
  const a = await repo.createSubmission({ activityId: activity.id, hours: 4, serviceDate: '2026-11-01', evidenceNote: 'x' });
  const b = await repo.createSubmission({ activityId: activity.id, hours: 3, serviceDate: '2026-11-01', evidenceNote: 'x' });
  await repo.transition(b.id, 'SUBMITTED', { status: 'REJECTED' });

  assert.equal(await repo.sumHoursForDay(1, '2026-11-01'), 4);
  assert.equal(await repo.sumHoursForDay(1, '2026-11-01', a.id), 0);
  assert.equal(await repo.sumHoursForDay(1, '2026-11-02'), 0);
});
