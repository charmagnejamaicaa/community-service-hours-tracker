'use strict';

/**
 * The daily automation job (SA7). It decides WHO gets a notification and then hands each one to
 * sendNotification (retries, logs, manual review) from notificationService.js.
 *
 *  1. Deadline reminders      - students still short on hours whose deadline is close
 *  2. Missing-verification    - submissions that have waited too long for a supervisor
 *  3. Progress summary        - counts per status, written to the log and returned
 *
 * One bad item never stops the others. Running the job twice on the same day does not send
 * duplicates (the notification key includes the date).
 */

const { computeProgress } = require('./hoursService');
const { sendNotification } = require('./notificationService');
const { buildProgressSummary, toDateString } = require('./progressSummary');

const DAY_MS = 24 * 60 * 60 * 1000;

async function runDailyJob({ repo, store, provider, logger, config, now = () => new Date(), sleep }) {
  const today = now();
  const counters = { duplicates: 0 };

  // Same logger, but it also counts duplicate-skipped events so the result can report them.
  const sendLogger = {
    info(event, fields) {
      if (event === 'notification_duplicate_skipped') counters.duplicates += 1;
      logger.info(event, fields);
    },
    warn: (event, fields) => logger.warn(event, fields),
    error: (event, fields) => logger.error(event, fields),
  };

  const sendOptions = {
    provider,
    store,
    logger: sendLogger,
    maxAttempts: config.maxAttempts,
    delaysMs: config.delaysMs,
    now,
    ...(sleep ? { sleep } : {}),
  };

  async function deliver(type, targets) {
    const result = { considered: targets.length, sent: 0, manualReview: 0, duplicates: 0, errors: 0 };
    for (const target of targets) {
      const duplicatesBefore = counters.duplicates;
      try {
        const notification = await sendNotification(
          { type, studentId: target.studentId, referenceId: target.referenceId, message: target.message },
          sendOptions
        );
        if (counters.duplicates > duplicatesBefore) {
          result.duplicates += 1;
        } else if (notification.status === 'SENT') {
          result.sent += 1;
        } else if (notification.status === 'MANUAL_REVIEW') {
          result.manualReview += 1;
        }
      } catch (err) {
        result.errors += 1;
        logger.error('job_item_failed', { type, studentId: target.studentId, reason: err.message });
      }
    }
    return result;
  }

  const students = await repo.listStudentsWithHours();

  // 1. Deadline reminders
  const reminderTargets = [];
  for (const student of students) {
    const progress = computeProgress({
      requiredHours: student.requiredHours,
      approvedHours: student.approvedHours,
      pendingHours: student.pendingHours,
      deadline: student.deadline,
      today,
    });
    const inWindow =
      progress.daysLeft !== null &&
      progress.daysLeft >= 0 &&
      progress.daysLeft <= config.reminderDaysBeforeDeadline;
    if (progress.status === 'IN_PROGRESS' && inWindow) {
      reminderTargets.push({
        studentId: student.id,
        referenceId: `deadline-${toDateString(student.deadline)}`,
        message: `${progress.remainingHours} hours remaining, ${progress.daysLeft} days left.`,
      });
    }
  }
  const deadlineReminders = await deliver('DEADLINE_REMINDER', reminderTargets);

  // 2. Missing-verification alerts
  const cutoff = new Date(today.getTime() - config.verificationAlertDays * DAY_MS);
  const staleSubmissions = await repo.listStaleSubmissions(cutoff);
  const alertTargets = staleSubmissions.map((s) => ({
    studentId: s.studentId,
    referenceId: `submission-${s.id}`,
    message: 'A submission is waiting for verification.',
  }));
  const verificationAlerts = await deliver('MISSING_VERIFICATION_ALERT', alertTargets);

  // 3. Progress summary (counts only)
  const summary = buildProgressSummary(students, today);
  logger.info('progress_summary', summary.totals);

  return {
    ranAt: today.toISOString(),
    deadlineReminders,
    verificationAlerts,
    summary: summary.totals,
  };
}

module.exports = { runDailyJob };
