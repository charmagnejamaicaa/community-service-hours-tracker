'use strict';

/**
 * The rules of the hours workflow, in one place (pure code: easy to test and to explain).
 *
 *   SUBMITTED --verify--> VERIFIED --approve--> APPROVED
 *   SUBMITTED --reject--> REJECTED
 *   VERIFIED  --return--> REJECTED         (coordinator sends it back for correction)
 *   REJECTED  --resubmit--> SUBMITTED      (student fixes it and sends it again)
 *
 * APPROVED is final. Only APPROVED hours count toward the requirement.
 */

const ROLES = {
  STUDENT: 'student',
  SUPERVISOR: 'supervisor',
  COORDINATOR: 'coordinator',
};

const STATUSES = ['SUBMITTED', 'VERIFIED', 'APPROVED', 'REJECTED'];

const TRANSITIONS = {
  verify: { role: ROLES.SUPERVISOR, from: ['SUBMITTED'], to: 'VERIFIED' },
  reject: { role: ROLES.SUPERVISOR, from: ['SUBMITTED'], to: 'REJECTED' },
  approve: { role: ROLES.COORDINATOR, from: ['VERIFIED'], to: 'APPROVED' },
  return: { role: ROLES.COORDINATOR, from: ['VERIFIED'], to: 'REJECTED' },
  resubmit: { role: ROLES.STUDENT, from: ['REJECTED'], to: 'SUBMITTED' },
};

/** @returns {{ok: true, to: string} | {ok: false}} */
function nextStatus(action, currentStatus) {
  const rule = TRANSITIONS[action];
  if (!rule) throw new Error(`Unknown action: ${action}`);
  return rule.from.includes(currentStatus) ? { ok: true, to: rule.to } : { ok: false };
}

module.exports = { ROLES, STATUSES, TRANSITIONS, nextStatus };
