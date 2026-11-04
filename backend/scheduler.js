'use strict';

/**
 * createJobRunner: runs the job, but never two at the same time (a manual run while the
 * scheduled run is still going is skipped, not doubled).
 *
 * startScheduler: runs the job on a schedule using node-cron (passed in, so tests can fake it).
 */

function createJobRunner({ run, logger }) {
  let running = false;

  return {
    get isRunning() {
      return running;
    },

    /** trigger is "schedule" or "manual" (it shows up in the logs). */
    async runOnce(trigger) {
      if (running) {
        logger.warn('job_skipped_already_running', { trigger });
        return { skipped: true };
      }
      running = true;
      const startedAt = Date.now();
      logger.info('job_started', { trigger });
      try {
        const result = await run();
        logger.info('job_finished', { trigger, durationMs: Date.now() - startedAt });
        return { skipped: false, result };
      } catch (err) {
        logger.error('job_failed', { trigger, reason: err.message });
        throw err;
      } finally {
        running = false;
      }
    },
  };
}

function startScheduler({ cron, runner, expression, timezone, logger }) {
  if (!cron.validate(expression)) {
    throw new Error(`Invalid cron expression: ${expression}`);
  }
  const task = cron.schedule(
    expression,
    () => {
      // Failures are already logged by the runner; a failed run must never crash the server.
      runner.runOnce('schedule').catch(() => {});
    },
    { timezone }
  );
  logger.info('scheduler_started', { expression, timezone });
  return task;
}

module.exports = { createJobRunner, startScheduler };
