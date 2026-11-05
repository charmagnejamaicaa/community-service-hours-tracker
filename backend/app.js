'use strict';

const express = require('express');
const cors = require('cors');
const crypto = require('node:crypto');
const { computeProgress } = require('./hoursService');
const { buildProgressSummary } = require('./progressSummary');
const { registerWorkflowRoutes } = require('./workflowRoutes');

/**
 * Builds the Express app. Dependencies are passed in so tests can use fakes:
 *   getPool   - returns the MySQL pool (see db.js)
 *   store     - notification store (MySQL or in-memory)
 *   logger    - structured logger (see logger.js)
 *   repo      - job queries (see repoMysql.js)
 *   jobRunner - runs the daily job (see scheduler.js)
 *   workflowRepo - activities and submissions (see workflowRepo.js)
 */
function createApp({ getPool, store, logger, repo, jobRunner, workflowRepo }) {
  const app = express();
  app.use(cors());
  app.use(express.json());

  // Give every request a reference ID so errors can be matched to log lines.
  app.use((req, res, next) => {
    req.referenceId = `req-${crypto.randomBytes(4).toString('hex')}`;
    next();
  });

  function sendError(req, res, status, code, message) {
    res.status(status).json({ error: { code, message, referenceId: req.referenceId } });
  }

  function parseId(value) {
    const id = Number(value);
    return Number.isInteger(id) && id > 0 ? id : null;
  }

  // --- Health ---
  app.get('/api/health', (req, res) => {
    res.json({ status: 'ok', time: new Date().toISOString() });
  });

  // --- Student progress: required, approved, pending, remaining hours ---
  app.get('/api/students/:id/progress', async (req, res) => {
    const studentId = parseId(req.params.id);
    if (!studentId) {
      return sendError(req, res, 400, 'VALIDATION_ERROR', 'Student id must be a positive integer.');
    }

    try {
      const pool = getPool();

      const [students] = await pool.query(
        'SELECT id, required_hours, deadline FROM students WHERE id = ?',
        [studentId]
      );
      if (students.length === 0) {
        return sendError(req, res, 404, 'NOT_FOUND', 'Student not found.');
      }
      const student = students[0];

      const [sums] = await pool.query(
        `SELECT
           COALESCE(SUM(CASE WHEN s.status = 'APPROVED' THEN s.hours END), 0) AS approved,
           COALESCE(SUM(CASE WHEN s.status IN ('SUBMITTED', 'VERIFIED') THEN s.hours END), 0) AS pending
         FROM submissions s
         JOIN activities a ON a.id = s.activity_id
         WHERE a.student_id = ?`,
        [studentId]
      );

      const progress = computeProgress({
        requiredHours: student.required_hours,
        approvedHours: Number(sums[0].approved),
        pendingHours: Number(sums[0].pending),
        deadline: student.deadline,
      });

      logger.info('progress_calculated', { referenceId: req.referenceId, studentId, status: progress.status });

      const deadline = student.deadline ? new Date(student.deadline).toISOString().slice(0, 10) : null;
      res.json({ studentId, ...progress, deadline });
    } catch (err) {
      logger.error('progress_failed', { referenceId: req.referenceId, studentId, reason: err.message });
      sendError(req, res, 500, 'SERVER_ERROR', 'Something went wrong. Please try again.');
    }
  });

  // --- Progress summary for all students (coordinator / adviser) ---
  app.get('/api/summaries/progress', async (req, res) => {
    try {
      const students = await repo.listStudentsWithHours();
      res.json(buildProgressSummary(students, new Date()));
    } catch (err) {
      logger.error('summary_failed', { referenceId: req.referenceId, reason: err.message });
      sendError(req, res, 500, 'SERVER_ERROR', 'Something went wrong. Please try again.');
    }
  });

  // --- Run the daily job now (for demos and testing) ---
  // Week 13 only: the role comes from a demo header. Real authentication and authorization
  // replace this in Week 14.
  app.post('/api/jobs/run', async (req, res) => {
    const role = String(req.get('X-Demo-Role') || '').toLowerCase();
    if (role !== 'coordinator') {
      logger.warn('job_run_forbidden', { referenceId: req.referenceId });
      return sendError(req, res, 403, 'FORBIDDEN', 'Only a coordinator can run the job.');
    }

    try {
      const outcome = await jobRunner.runOnce('manual');
      if (outcome.skipped) {
        return sendError(req, res, 409, 'JOB_RUNNING', 'The job is already running. Try again in a moment.');
      }
      res.json(outcome.result);
    } catch (err) {
      logger.error('job_run_error', { referenceId: req.referenceId, reason: err.message });
      sendError(req, res, 500, 'SERVER_ERROR', 'The job failed. Check the server logs.');
    }
  });

  // --- Activities and submissions: register, submit, verify, approve, correct ---
  if (workflowRepo) {
    registerWorkflowRoutes(app, { workflowRepo, logger, sendError, parseId });
  }

  // --- Manual review queue: notifications that failed permanently ---
  // The message text is NOT returned (data minimization): the coordinator only needs to know
  // who, what kind, and why it failed.
  app.get('/api/notifications/failed', async (req, res) => {
    try {
      const items = await store.listByStatus('MANUAL_REVIEW');
      res.json({
        count: items.length,
        items: items.map((n) => ({
          id: n.id,
          type: n.type,
          studentId: n.studentId,
          referenceId: n.referenceId,
          attempts: n.attempts,
          reason: n.lastError,
          status: n.status,
          createdAt: n.createdAt,
        })),
      });
    } catch (err) {
      logger.error('failed_list_error', { referenceId: req.referenceId, reason: err.message });
      sendError(req, res, 500, 'SERVER_ERROR', 'Something went wrong. Please try again.');
    }
  });

  // --- Coordinator marks a failed notification as handled ---
  app.patch('/api/notifications/:id/handled', async (req, res) => {
    const id = parseId(req.params.id);
    if (!id) {
      return sendError(req, res, 400, 'VALIDATION_ERROR', 'Notification id must be a positive integer.');
    }

    try {
      const current = await store.get(id);
      if (!current) {
        return sendError(req, res, 404, 'NOT_FOUND', 'Notification not found.');
      }
      if (current.status !== 'MANUAL_REVIEW') {
        return sendError(req, res, 409, 'INVALID_STATE', `Only notifications in MANUAL_REVIEW can be handled (current: ${current.status}).`);
      }

      await store.update(id, { status: 'HANDLED', handledAt: new Date().toISOString() });
      logger.info('notification_handled', { referenceId: req.referenceId, notificationId: id });
      res.json({ id, status: 'HANDLED' });
    } catch (err) {
      logger.error('handled_error', { referenceId: req.referenceId, notificationId: id, reason: err.message });
      sendError(req, res, 500, 'SERVER_ERROR', 'Something went wrong. Please try again.');
    }
  });

  return app;
}

module.exports = { createApp };