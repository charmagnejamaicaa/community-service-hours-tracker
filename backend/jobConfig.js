'use strict';

/**
 * Reads the job settings from environment variables (.env), with safe defaults.
 * Changing the policy never needs a code change (see docs/maintainability-note.md).
 */

const DEFAULT_DELAYS_MS = [60 * 1000, 5 * 60 * 1000]; // 1 minute, then 5 minutes

function parseWholeNumber(value, fallback) {
  const n = Number.parseInt(value, 10);
  return Number.isFinite(n) && n >= 0 ? n : fallback;
}

/** "500,1000" -> [500, 1000]. Anything invalid falls back to the default policy. */
function parseDelays(text) {
  if (typeof text !== 'string' || text.trim() === '') return [...DEFAULT_DELAYS_MS];
  const parts = text.split(',').map((p) => Number(p.trim()));
  const valid = parts.length > 0 && parts.every((n) => Number.isFinite(n) && n >= 0);
  return valid ? parts : [...DEFAULT_DELAYS_MS];
}

function loadJobConfig(env = process.env) {
  return {
    reminderDaysBeforeDeadline: parseWholeNumber(env.REMINDER_DAYS_BEFORE_DEADLINE, 7),
    verificationAlertDays: parseWholeNumber(env.VERIFICATION_ALERT_DAYS, 3),
    maxAttempts: Math.max(1, parseWholeNumber(env.RETRY_MAX_ATTEMPTS, 3)),
    delaysMs: parseDelays(env.RETRY_DELAYS_MS),
    providerMode: env.NOTIFICATION_MODE || 'ok', // ok | timeout_once | always_down | reject (fake provider)
    cronExpression: env.DAILY_JOB_CRON || '0 8 * * *', // every day at 08:00
    timezone: env.JOB_TIMEZONE || 'Asia/Manila',
  };
}

module.exports = { loadJobConfig, parseDelays, DEFAULT_DELAYS_MS };
