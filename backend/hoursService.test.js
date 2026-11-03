'use strict';

/**
 * Business rules for service hours.
 * Pure functions only (no database, no HTTP), so they are easy to test.
 */

const MAX_HOURS_PER_SUBMISSION = 12;
const MAX_EVIDENCE_LENGTH = 500;
const MAX_DAYS_BACK = 30;

function round2(n) {
  return Math.round((n + Number.EPSILON) * 100) / 100;
}

function assertNonNegativeNumber(value, name) {
  if (typeof value !== 'number' || !Number.isFinite(value) || value < 0) {
    throw new RangeError(`${name} must be a number that is 0 or greater`);
  }
}

/**
 * Only APPROVED hours count toward completion.
 * remainingHours = max(requiredHours - approvedHours, 0)
 */
function calculateRemainingHours(requiredHours, approvedHours) {
  assertNonNegativeNumber(requiredHours, 'requiredHours');
  assertNonNegativeNumber(approvedHours, 'approvedHours');
  return round2(Math.max(requiredHours - approvedHours, 0));
}

function daysBetween(fromDate, toDate) {
  const MS_PER_DAY = 24 * 60 * 60 * 1000;
  const from = Date.UTC(fromDate.getUTCFullYear(), fromDate.getUTCMonth(), fromDate.getUTCDate());
  const to = Date.UTC(toDate.getUTCFullYear(), toDate.getUTCMonth(), toDate.getUTCDate());
  return Math.round((to - from) / MS_PER_DAY);
}

/**
 * Build the progress summary shown on the student dashboard.
 * status: COMPLETED | OVERDUE | IN_PROGRESS
 */
function computeProgress({ requiredHours, approvedHours, pendingHours = 0, deadline = null, today = new Date() }) {
  assertNonNegativeNumber(requiredHours, 'requiredHours');
  assertNonNegativeNumber(approvedHours, 'approvedHours');
  assertNonNegativeNumber(pendingHours, 'pendingHours');

  const remainingHours = calculateRemainingHours(requiredHours, approvedHours);
  const percentComplete =
    requiredHours === 0 ? 100 : Math.min(100, round2((approvedHours / requiredHours) * 100));

  let daysLeft = null;
  if (deadline) {
    daysLeft = daysBetween(today, new Date(deadline));
  }

  let status = 'IN_PROGRESS';
  if (remainingHours === 0) {
    status = 'COMPLETED';
  } else if (daysLeft !== null && daysLeft < 0) {
    status = 'OVERDUE';
  }

  return {
    requiredHours: round2(requiredHours),
    approvedHours: round2(approvedHours),
    pendingHours: round2(pendingHours),
    remainingHours,
    percentComplete,
    daysLeft,
    status,
  };
}

/**
 * Validate a hour submission. Returns a list of error messages (empty list = valid).
 */
function validateSubmission({ hours, serviceDate, evidenceNote }, today = new Date()) {
  const errors = [];

  if (typeof hours !== 'number' || !Number.isFinite(hours) || hours <= 0 || hours > MAX_HOURS_PER_SUBMISSION) {
    errors.push(`Hours must be greater than 0 and at most ${MAX_HOURS_PER_SUBMISSION}.`);
  }

  const date = new Date(serviceDate);
  if (!serviceDate || Number.isNaN(date.getTime())) {
    errors.push('Service date is required and must be a valid date.');
  } else {
    const age = daysBetween(date, today);
    if (age < 0) {
      errors.push('Service date cannot be in the future.');
    } else if (age > MAX_DAYS_BACK) {
      errors.push(`Service date cannot be older than ${MAX_DAYS_BACK} days.`);
    }
  }

  if (typeof evidenceNote !== 'string' || evidenceNote.trim() === '') {
    errors.push('Evidence note is required.');
  } else if (evidenceNote.length > MAX_EVIDENCE_LENGTH) {
    errors.push(`Evidence note must be at most ${MAX_EVIDENCE_LENGTH} characters.`);
  }

  return errors;
}

module.exports = {
  MAX_HOURS_PER_SUBMISSION,
  MAX_EVIDENCE_LENGTH,
  MAX_DAYS_BACK,
  calculateRemainingHours,
  computeProgress,
  validateSubmission,
};
