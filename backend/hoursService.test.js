'use strict';

// Run with: npm test   (uses Node's built-in test runner, Node 18+)
const test = require('node:test');
const assert = require('node:assert/strict');
const { calculateRemainingHours, computeProgress, validateSubmission } = require('./hoursService');

const TODAY = new Date('2026-11-03T00:00:00Z');

test('remaining hours: normal case', () => {
  assert.equal(calculateRemainingHours(40, 22.5), 17.5);
});

test('remaining hours: never goes below zero', () => {
  assert.equal(calculateRemainingHours(40, 45), 0);
});

test('remaining hours: handles decimals without float errors', () => {
  assert.equal(calculateRemainingHours(1, 0.9), 0.1);
});

test('remaining hours: rejects negative or non-number input', () => {
  assert.throws(() => calculateRemainingHours(-1, 0), RangeError);
  assert.throws(() => calculateRemainingHours(40, '10'), RangeError);
  assert.throws(() => calculateRemainingHours(40, NaN), RangeError);
});

test('progress: in progress with pending hours not counted', () => {
  const p = computeProgress({
    requiredHours: 40,
    approvedHours: 22.5,
    pendingHours: 4.5,
    deadline: '2026-12-15',
    today: TODAY,
  });
  assert.equal(p.remainingHours, 17.5);
  assert.equal(p.pendingHours, 4.5);
  assert.equal(p.percentComplete, 56.25);
  assert.equal(p.daysLeft, 42);
  assert.equal(p.status, 'IN_PROGRESS');
});

test('progress: completed when approved hours reach the requirement', () => {
  const p = computeProgress({ requiredHours: 40, approvedHours: 40, today: TODAY });
  assert.equal(p.status, 'COMPLETED');
  assert.equal(p.remainingHours, 0);
  assert.equal(p.percentComplete, 100);
});

test('progress: overdue when deadline passed and hours remain', () => {
  const p = computeProgress({
    requiredHours: 40,
    approvedHours: 10,
    deadline: '2026-10-30',
    today: TODAY,
  });
  assert.equal(p.status, 'OVERDUE');
  assert.equal(p.daysLeft, -4);
});

test('progress: completed beats overdue', () => {
  const p = computeProgress({
    requiredHours: 40,
    approvedHours: 40,
    deadline: '2026-10-30',
    today: TODAY,
  });
  assert.equal(p.status, 'COMPLETED');
});

test('validation: a valid submission has no errors', () => {
  const errors = validateSubmission(
    { hours: 4.5, serviceDate: '2026-11-01', evidenceNote: 'Tree planting, signed attendance sheet' },
    TODAY
  );
  assert.deepEqual(errors, []);
});

test('validation: rejects zero, negative, and over-limit hours', () => {
  for (const hours of [0, -2, 13]) {
    const errors = validateSubmission({ hours, serviceDate: '2026-11-01', evidenceNote: 'ok' }, TODAY);
    assert.equal(errors.length, 1, `hours=${hours}`);
  }
});

test('validation: rejects future and too-old dates', () => {
  const future = validateSubmission({ hours: 2, serviceDate: '2026-11-10', evidenceNote: 'ok' }, TODAY);
  const old = validateSubmission({ hours: 2, serviceDate: '2026-09-01', evidenceNote: 'ok' }, TODAY);
  assert.equal(future.length, 1);
  assert.equal(old.length, 1);
});

test('validation: rejects missing evidence note', () => {
  const errors = validateSubmission({ hours: 2, serviceDate: '2026-11-01', evidenceNote: '   ' }, TODAY);
  assert.equal(errors.length, 1);
});

test('validation: collects several errors at once', () => {
  const errors = validateSubmission({ hours: -1, serviceDate: 'not-a-date', evidenceNote: '' }, TODAY);
  assert.equal(errors.length, 3);
});
