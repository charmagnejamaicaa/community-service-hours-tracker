'use strict';

/**
 * The job's database queries. Kept separate from dailyJob.js so the job logic can be tested
 * with a fake repo (no MySQL needed).
 */
function createMysqlRepo(getPool) {
  return {
    /** Every student with approved and pending hours added up. */
    async listStudentsWithHours() {
      const [rows] = await getPool().query(
        `SELECT
           st.id,
           st.required_hours,
           st.deadline,
           COALESCE(SUM(CASE WHEN s.status = 'APPROVED' THEN s.hours END), 0) AS approved,
           COALESCE(SUM(CASE WHEN s.status IN ('SUBMITTED', 'VERIFIED') THEN s.hours END), 0) AS pending
         FROM students st
         LEFT JOIN activities a ON a.student_id = st.id
         LEFT JOIN submissions s ON s.activity_id = a.id
         GROUP BY st.id, st.required_hours, st.deadline
         ORDER BY st.id`
      );
      return rows.map((r) => ({
        id: r.id,
        requiredHours: Number(r.required_hours),
        approvedHours: Number(r.approved),
        pendingHours: Number(r.pending),
        deadline: r.deadline,
      }));
    },

    /** Submissions still SUBMITTED (not verified) that were created at or before the cutoff. */
    async listStaleSubmissions(cutoff) {
      const [rows] = await getPool().query(
        `SELECT s.id, a.student_id, s.created_at
         FROM submissions s
         JOIN activities a ON a.id = s.activity_id
         WHERE s.status = 'SUBMITTED' AND s.created_at <= ?
         ORDER BY s.created_at, s.id`,
        [cutoff]
      );
      return rows.map((r) => ({ id: r.id, studentId: r.student_id, createdAt: r.created_at }));
    },
  };
}

module.exports = { createMysqlRepo };
