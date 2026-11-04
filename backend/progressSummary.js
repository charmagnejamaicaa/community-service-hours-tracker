'use strict';

const { computeProgress } = require('./hoursService');

/** Date or string -> "YYYY-MM-DD" (or null) */
function toDateString(value) {
  if (!value) return null;
  return new Date(value).toISOString().slice(0, 10);
}

/**
 * Progress summary for all students (the "progress summaries" automation).
 * Only IDs and numbers: no names, to keep personal data to a minimum.
 *
 * students: [{ id, requiredHours, approvedHours, pendingHours, deadline }]
 */
function buildProgressSummary(students, today = new Date()) {
  const rows = students.map((s) => {
    const p = computeProgress({
      requiredHours: s.requiredHours,
      approvedHours: s.approvedHours,
      pendingHours: s.pendingHours,
      deadline: s.deadline,
      today,
    });
    return { studentId: s.id, ...p, deadline: toDateString(s.deadline) };
  });

  const count = (status) => rows.filter((r) => r.status === status).length;
  const sum = (field) => Math.round(rows.reduce((total, r) => total + r[field], 0) * 100) / 100;

  return {
    generatedAt: today.toISOString(),
    totals: {
      students: rows.length,
      completed: count('COMPLETED'),
      inProgress: count('IN_PROGRESS'),
      overdue: count('OVERDUE'),
      approvedHours: sum('approvedHours'),
      remainingHours: sum('remainingHours'),
    },
    students: rows,
  };
}

module.exports = { buildProgressSummary, toDateString };
