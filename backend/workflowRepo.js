'use strict';

const { toDateString } = require('./progressSummary');

/**
 * MySQL version of the workflow repo. Same methods as workflowRepoMemory.js.
 * Uses the existing tables: students, activities, submissions (no schema change needed).
 */

// Only these fields may be updated; the map also protects against SQL injection through field names.
const UPDATABLE_COLUMNS = {
  status: 'status',
  rejectReason: 'reject_reason',
  hours: 'hours',
  serviceDate: 'service_date',
  evidenceNote: 'evidence_note',
};

const SUBMISSION_SELECT = `
  SELECT s.id, s.activity_id, a.student_id, s.hours, s.service_date,
         s.evidence_note, s.status, s.reject_reason, s.created_at
  FROM submissions s
  JOIN activities a ON a.id = s.activity_id`;

function toSubmission(row) {
  return {
    id: row.id,
    activityId: row.activity_id,
    studentId: row.student_id,
    hours: Number(row.hours),
    serviceDate: toDateString(row.service_date),
    evidenceNote: row.evidence_note,
    status: row.status,
    rejectReason: row.reject_reason,
    createdAt: new Date(row.created_at).toISOString(),
  };
}

function createMysqlWorkflowRepo(getPool) {
  const repo = {
    async findStudent(id) {
      const [rows] = await getPool().query('SELECT id FROM students WHERE id = ?', [id]);
      return rows.length ? { id: rows[0].id } : null;
    },

    async createActivity({ studentId, name, organization, activityDate }) {
      const [result] = await getPool().query(
        'INSERT INTO activities (student_id, name, organization, activity_date) VALUES (?, ?, ?, ?)',
        [studentId, name, organization, activityDate]
      );
      return { id: result.insertId, studentId, name, organization, activityDate };
    },

    async getActivity(id) {
      const [rows] = await getPool().query('SELECT id, student_id FROM activities WHERE id = ?', [id]);
      return rows.length ? { id: rows[0].id, studentId: rows[0].student_id } : null;
    },

    async sumHoursForDay(studentId, serviceDate, excludeSubmissionId = null) {
      const [rows] = await getPool().query(
        `SELECT COALESCE(SUM(s.hours), 0) AS total
         FROM submissions s
         JOIN activities a ON a.id = s.activity_id
         WHERE a.student_id = ? AND s.service_date = ? AND s.status <> 'REJECTED' AND s.id <> ?`,
        [studentId, serviceDate, excludeSubmissionId || 0]
      );
      return Number(rows[0].total);
    },

    async createSubmission({ activityId, hours, serviceDate, evidenceNote }) {
      const [result] = await getPool().query(
        'INSERT INTO submissions (activity_id, hours, service_date, evidence_note) VALUES (?, ?, ?, ?)',
        [activityId, hours, serviceDate, evidenceNote]
      );
      return repo.getSubmission(result.insertId);
    },

    async getSubmission(id) {
      const [rows] = await getPool().query(`${SUBMISSION_SELECT} WHERE s.id = ?`, [id]);
      return rows.length ? toSubmission(rows[0]) : null;
    },

    async listSubmissions(status) {
      const [rows] = await getPool().query(
        `${SUBMISSION_SELECT} WHERE s.status = ? ORDER BY s.created_at, s.id`,
        [status]
      );
      return rows.map(toSubmission);
    },

    /**
     * Change a submission ONLY if it is still in `fromStatus` (the WHERE clause does the check),
     * so two people acting at the same time cannot both succeed.
     * Returns true if it changed, false if someone else changed it first.
     */
    async transition(id, fromStatus, patch) {
      const sets = [];
      const values = [];
      for (const [field, value] of Object.entries(patch)) {
        const column = UPDATABLE_COLUMNS[field];
        if (!column) continue;
        sets.push(`${column} = ?`);
        values.push(value);
      }
      if (sets.length === 0) return false;
      const [result] = await getPool().query(
        `UPDATE submissions SET ${sets.join(', ')} WHERE id = ? AND status = ?`,
        [...values, id, fromStatus]
      );
      return result.affectedRows === 1;
    },

    async getStudentHours(studentId) {
      const [rows] = await getPool().query(
        `SELECT
           st.id, st.required_hours, st.deadline,
           COALESCE(SUM(CASE WHEN s.status = 'APPROVED' THEN s.hours END), 0) AS approved,
           COALESCE(SUM(CASE WHEN s.status IN ('SUBMITTED', 'VERIFIED') THEN s.hours END), 0) AS pending
         FROM students st
         LEFT JOIN activities a ON a.student_id = st.id
         LEFT JOIN submissions s ON s.activity_id = a.id
         WHERE st.id = ?
         GROUP BY st.id, st.required_hours, st.deadline`,
        [studentId]
      );
      if (rows.length === 0) return null;
      const r = rows[0];
      return {
        id: r.id,
        requiredHours: Number(r.required_hours),
        approvedHours: Number(r.approved),
        pendingHours: Number(r.pending),
        deadline: r.deadline,
      };
    },
  };
  return repo;
}

module.exports = { createMysqlWorkflowRepo };
