'use strict';

/**
 * In-memory version of the workflow repo, for tests and the demo (no database needed).
 * It has the SAME methods as workflowRepo.js (the MySQL version).
 *
 * students: [{ id, requiredHours, deadline }]
 */
const UPDATABLE = ['status', 'rejectReason', 'hours', 'serviceDate', 'evidenceNote'];

function createMemoryWorkflowRepo({ students = [] } = {}) {
  const studentMap = new Map(students.map((s) => [s.id, { ...s }]));
  const activities = new Map();
  const submissions = new Map();
  let nextActivityId = 1;
  let nextSubmissionId = 1;

  const copy = (item) => (item ? { ...item } : null);

  return {
    async findStudent(id) {
      return copy(studentMap.get(id));
    },

    async createActivity({ studentId, name, organization, activityDate }) {
      const activity = { id: nextActivityId++, studentId, name, organization, activityDate };
      activities.set(activity.id, activity);
      return copy(activity);
    },

    async getActivity(id) {
      return copy(activities.get(id));
    },

    /** Hours already on that date for the student, ignoring REJECTED ones and one excluded id. */
    async sumHoursForDay(studentId, serviceDate, excludeSubmissionId = null) {
      let total = 0;
      for (const s of submissions.values()) {
        const activity = activities.get(s.activityId);
        if (
          activity.studentId === studentId &&
          s.serviceDate === serviceDate &&
          s.status !== 'REJECTED' &&
          s.id !== excludeSubmissionId
        ) {
          total += s.hours;
        }
      }
      return total;
    },

    async createSubmission({ activityId, hours, serviceDate, evidenceNote }) {
      const activity = activities.get(activityId);
      const submission = {
        id: nextSubmissionId++,
        activityId,
        studentId: activity.studentId,
        hours,
        serviceDate,
        evidenceNote,
        status: 'SUBMITTED',
        rejectReason: null,
        createdAt: new Date().toISOString(),
      };
      submissions.set(submission.id, submission);
      return copy(submission);
    },

    async getSubmission(id) {
      return copy(submissions.get(id));
    },

    async listSubmissions(status) {
      return [...submissions.values()].filter((s) => s.status === status).map(copy);
    },

    /**
     * Change a submission ONLY if it is still in `fromStatus`.
     * Returns true if it changed, false if someone else changed it first.
     */
    async transition(id, fromStatus, patch) {
      const submission = submissions.get(id);
      if (!submission || submission.status !== fromStatus) return false;
      for (const key of UPDATABLE) {
        if (key in patch) submission[key] = patch[key];
      }
      return true;
    },

    async getStudentHours(studentId) {
      const student = studentMap.get(studentId);
      if (!student) return null;
      let approved = 0;
      let pending = 0;
      for (const s of submissions.values()) {
        if (activities.get(s.activityId).studentId !== studentId) continue;
        if (s.status === 'APPROVED') approved += s.hours;
        if (s.status === 'SUBMITTED' || s.status === 'VERIFIED') pending += s.hours;
      }
      return {
        id: student.id,
        requiredHours: student.requiredHours,
        approvedHours: approved,
        pendingHours: pending,
        deadline: student.deadline ?? null,
      };
    },
  };
}

module.exports = { createMemoryWorkflowRepo };
