'use strict';

require('dotenv').config();

const express = require('express');
const cors = require('cors');
const crypto = require('node:crypto');
const logger = require('./logger');
const { getPool } = require('./db');
const { computeProgress } = require('./hoursService');

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

// Health check
app.get('/api/health', (req, res) => {
  res.json({ status: 'ok', time: new Date().toISOString() });
});

// Student progress: required, approved, pending, remaining hours
app.get('/api/students/:id/progress', async (req, res) => {
  const studentId = Number(req.params.id);
  if (!Number.isInteger(studentId) || studentId <= 0) {
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

    logger.info('progress_calculated', {
      referenceId: req.referenceId,
      studentId,
      status: progress.status,
    });

    res.json({ studentId, ...progress, deadline: student.deadline });
  } catch (err) {
    logger.error('progress_failed', { referenceId: req.referenceId, studentId, reason: err.message });
    sendError(req, res, 500, 'SERVER_ERROR', 'Something went wrong. Please try again.');
  }
});

const PORT = Number(process.env.PORT) || 3000;
app.listen(PORT, () => {
  logger.info('server_started', { port: PORT });
});

module.exports = app;
