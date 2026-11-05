'use strict';

const { computeProgress, validateSubmission, MAX_HOURS_PER_SUBMISSION } = require('./hoursService');
const { nextStatus, ROLES, STATUSES } = require('./workflow');
const { toDateString } = require('./progressSummary');

const MAX_TEXT = 150;
const MAX_REASON = 255;
const KNOWN_ROLES = Object.values(ROLES);

const round2 = (n) => Math.round((n + Number.EPSILON) * 100) / 100;
const isDateText = (v) => typeof v === 'string' && /^\d{4}-\d{2}-\d{2}$/.test(v) && !Number.isNaN(new Date(v).getTime());
const isText = (v, max) => typeof v === 'string' && v.trim() !== '' && v.trim().length <= max;

/**
 * Adds the workflow endpoints to the Express app.
 *
 * Week 13 only: the caller's role comes from the demo header X-Demo-Role
 * (student | supervisor | coordinator). Real authentication replaces `requireRole` in Week 14.
 */
function registerWorkflowRoutes(app, { workflowRepo: repo, logger, sendError, parseId, now = () => new Date() }) {
  function requireRole(...allowed) {
    return (req, res, next) => {
      const role = String(req.get('X-Demo-Role') || '').toLowerCase();
      if (!allowed.includes(role)) {
        // Only log known role names (never raw header text).
        logger.warn('forbidden', {
          referenceId: req.referenceId,
          method: req.method,
          route: req.route && req.route.path,
          role: KNOWN_ROLES.includes(role) ? role : 'none',
        });
        return sendError(req, res, 403, 'FORBIDDEN', `This action needs the role: ${allowed.join(' or ')}.`);
      }
      req.role = role;
      next();
    };
  }

  function sendValidation(req, res, errors) {
    res.status(400).json({
      error: {
        code: 'VALIDATION_ERROR',
        message: errors.join(' '),
        details: errors,
        referenceId: req.referenceId,
      },
    });
  }

  // Wraps a route so that any unexpected error becomes a clean 500 (no internal details leaked).
  const handle = (fn) => async (req, res) => {
    try {
      await fn(req, res);
    } catch (err) {
      logger.error('workflow_error', { referenceId: req.referenceId, route: req.route && req.route.path, reason: err.message });
      sendError(req, res, 500, 'SERVER_ERROR', 'Something went wrong. Please try again.');
    }
  };

  /** Checks the 12-hours-per-day rule for one student and date. Returns an error text or null. */
  async function dailyLimitError(studentId, serviceDate, hours, excludeSubmissionId = null) {
    const already = await repo.sumHoursForDay(studentId, serviceDate, excludeSubmissionId);
    if (round2(already + hours) > MAX_HOURS_PER_SUBMISSION) {
      return `Daily limit: ${round2(already)} hours are already recorded for ${serviceDate} (maximum ${MAX_HOURS_PER_SUBMISSION} per day).`;
    }
    return null;
  }

  /** Looks up a submission, checks the workflow rule, and applies the change safely. */
  async function applyTransition(req, res, { id, action, patch = {} }) {
    const submission = await repo.getSubmission(id);
    if (!submission) {
      sendError(req, res, 404, 'NOT_FOUND', 'Submission not found.');
      return null;
    }

    const step = nextStatus(action, submission.status);
    if (!step.ok) {
      sendError(req, res, 409, 'INVALID_STATE', `Cannot ${action} a submission that is ${submission.status}.`);
      return null;
    }

    const changed = await repo.transition(id, submission.status, { status: step.to, ...patch });
    if (!changed) {
      sendError(req, res, 409, 'INVALID_STATE', 'This submission was just changed by someone else. Reload and try again.');
      return null;
    }

    logger.info('submission_transition', {
      referenceId: req.referenceId,
      submissionId: id,
      action,
      from: submission.status,
      to: step.to,
      role: req.role,
    });
    return { submission, to: step.to };
  }

  function readReason(req, required) {
    const raw = (req.body || {}).reason;
    const reason = typeof raw === 'string' ? raw.trim() : '';
    const errors = [];
    if (required && reason === '') errors.push('A reason is required.');
    if (reason.length > MAX_REASON) errors.push(`Reason must be at most ${MAX_REASON} characters.`);
    return { reason, errors };
  }

  // --- Student registers an activity ---
  app.post(
    '/api/activities',
    requireRole(ROLES.STUDENT),
    handle(async (req, res) => {
      const { studentId, name, organization, activityDate } = req.body || {};
      const errors = [];
      if (!Number.isInteger(studentId) || studentId <= 0) errors.push('Student id must be a positive whole number.');
      if (!isText(name, MAX_TEXT)) errors.push(`Activity name is required (at most ${MAX_TEXT} characters).`);
      if (!isText(organization, MAX_TEXT)) errors.push(`Organization is required (at most ${MAX_TEXT} characters).`);
      if (!isDateText(activityDate)) errors.push('Activity date is required in the format YYYY-MM-DD.');
      if (errors.length > 0) return sendValidation(req, res, errors);

      if (!(await repo.findStudent(studentId))) {
        return sendError(req, res, 404, 'NOT_FOUND', 'Student not found.');
      }

      const activity = await repo.createActivity({
        studentId,
        name: name.trim(),
        organization: organization.trim(),
        activityDate,
      });
      logger.info('activity_registered', { referenceId: req.referenceId, activityId: activity.id, studentId });
      res.status(201).json(activity);
    })
  );

  // --- Student submits hours and evidence ---
  app.post(
    '/api/activities/:id/submissions',
    requireRole(ROLES.STUDENT),
    handle(async (req, res) => {
      const activityId = parseId(req.params.id);
      if (!activityId) return sendError(req, res, 400, 'VALIDATION_ERROR', 'Activity id must be a positive integer.');

      const { hours, serviceDate, evidenceNote } = req.body || {};
      const errors = validateSubmission({ hours, serviceDate, evidenceNote }, now());
      if (errors.length > 0) return sendValidation(req, res, errors);

      const activity = await repo.getActivity(activityId);
      if (!activity) return sendError(req, res, 404, 'NOT_FOUND', 'Activity not found.');

      const date = toDateString(serviceDate);
      const limitError = await dailyLimitError(activity.studentId, date, hours);
      if (limitError) return sendValidation(req, res, [limitError]);

      const submission = await repo.createSubmission({
        activityId,
        hours,
        serviceDate: date,
        evidenceNote: evidenceNote.trim(),
      });
      logger.info('submission_created', {
        referenceId: req.referenceId,
        submissionId: submission.id,
        activityId,
        hours,
      });
      res.status(201).json({
        id: submission.id,
        status: submission.status,
        hours: submission.hours,
        createdAt: submission.createdAt,
      });
    })
  );

  // --- Supervisor / coordinator: list submissions by status (default: waiting for verification) ---
  app.get(
    '/api/submissions',
    requireRole(ROLES.SUPERVISOR, ROLES.COORDINATOR),
    handle(async (req, res) => {
      const status = String(req.query.status || 'SUBMITTED').toUpperCase();
      if (!STATUSES.includes(status)) {
        return sendValidation(req, res, [`Status must be one of: ${STATUSES.join(', ')}.`]);
      }
      const items = await repo.listSubmissions(status);
      res.json({ count: items.length, items });
    })
  );

  // --- Supervisor verifies or rejects ---
  app.patch(
    '/api/submissions/:id/verify',
    requireRole(ROLES.SUPERVISOR),
    handle(async (req, res) => {
      const id = parseId(req.params.id);
      if (!id) return sendError(req, res, 400, 'VALIDATION_ERROR', 'Submission id must be a positive integer.');

      const decision = String((req.body || {}).decision || '').toUpperCase();
      if (decision !== 'VERIFY' && decision !== 'REJECT') {
        return sendValidation(req, res, ['Decision must be VERIFY or REJECT.']);
      }
      const { reason, errors } = readReason(req, decision === 'REJECT');
      if (errors.length > 0) return sendValidation(req, res, errors);

      const outcome = await applyTransition(req, res, {
        id,
        action: decision === 'VERIFY' ? 'verify' : 'reject',
        patch: decision === 'REJECT' ? { rejectReason: reason } : {},
      });
      if (outcome) res.json({ id, status: outcome.to });
    })
  );

  // --- Coordinator approves verified hours ---
  app.patch(
    '/api/submissions/:id/approve',
    requireRole(ROLES.COORDINATOR),
    handle(async (req, res) => {
      const id = parseId(req.params.id);
      if (!id) return sendError(req, res, 400, 'VALIDATION_ERROR', 'Submission id must be a positive integer.');

      const outcome = await applyTransition(req, res, { id, action: 'approve' });
      if (!outcome) return;

      // Event-driven trigger (docs/trigger-decision.md): recalculate the remaining hours right away.
      const studentId = outcome.submission.studentId;
      const row = await repo.getStudentHours(studentId);
      const progress = computeProgress({
        requiredHours: row.requiredHours,
        approvedHours: row.approvedHours,
        pendingHours: row.pendingHours,
        deadline: row.deadline,
        today: now(),
      });
      logger.info('hours_approved', {
        referenceId: req.referenceId,
        submissionId: id,
        studentId,
        remainingHours: progress.remainingHours,
        progressStatus: progress.status,
      });
      if (progress.status === 'COMPLETED') {
        logger.info('student_completed', { referenceId: req.referenceId, studentId });
      }
      res.json({
        id,
        status: 'APPROVED',
        studentId,
        remainingHours: progress.remainingHours,
        progressStatus: progress.status,
      });
    })
  );

  // --- Coordinator returns a verified submission for correction ---
  app.patch(
    '/api/submissions/:id/correct',
    requireRole(ROLES.COORDINATOR),
    handle(async (req, res) => {
      const id = parseId(req.params.id);
      if (!id) return sendError(req, res, 400, 'VALIDATION_ERROR', 'Submission id must be a positive integer.');

      const { reason, errors } = readReason(req, true);
      if (errors.length > 0) return sendValidation(req, res, errors);

      const outcome = await applyTransition(req, res, { id, action: 'return', patch: { rejectReason: reason } });
      if (outcome) res.json({ id, status: outcome.to });
    })
  );

  // --- Student fixes a rejected submission and sends it again ---
  app.patch(
    '/api/submissions/:id/resubmit',
    requireRole(ROLES.STUDENT),
    handle(async (req, res) => {
      const id = parseId(req.params.id);
      if (!id) return sendError(req, res, 400, 'VALIDATION_ERROR', 'Submission id must be a positive integer.');

      const { hours, serviceDate, evidenceNote } = req.body || {};
      const errors = validateSubmission({ hours, serviceDate, evidenceNote }, now());
      if (errors.length > 0) return sendValidation(req, res, errors);

      const current = await repo.getSubmission(id);
      if (!current) return sendError(req, res, 404, 'NOT_FOUND', 'Submission not found.');

      const date = toDateString(serviceDate);
      const limitError = await dailyLimitError(current.studentId, date, hours, id);
      if (limitError) return sendValidation(req, res, [limitError]);

      const outcome = await applyTransition(req, res, {
        id,
        action: 'resubmit',
        patch: { hours, serviceDate: date, evidenceNote: evidenceNote.trim(), rejectReason: null },
      });
      if (outcome) res.json({ id, status: outcome.to });
    })
  );
}

module.exports = { registerWorkflowRoutes };
