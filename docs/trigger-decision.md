# Trigger Decision

**Project 20 - Community Service Hours Tracker** | SA7 Week 13

## Decision

Use a **hybrid approach**: event-driven triggers for state changes, and a scheduled daily job for time-based checks.

## Triggers

| Automation | Trigger type | When it fires | Justification |
| --- | --- | --- | --- |
| Remaining-hour calculation | Event-driven | When a coordinator approves or corrects hours | The student should see updated progress immediately; no need to wait for a schedule. |
| Deadline reminder | Scheduled (daily, 08:00) | Student's deadline is within `REMINDER_DAYS_BEFORE_DEADLINE` days and hours are not complete | Time-based, no user action causes it. |
| Missing-verification alert | Scheduled (daily, 08:00) | A submission has stayed `SUBMITTED` for more than `VERIFICATION_ALERT_DAYS` days | Detects inaction, which an event cannot detect. |
| Progress summary | Scheduled (weekly, Monday 08:00) | Weekly | Digest for students, coordinators, and advisers. |

## Alternatives Considered

- **Pure event-driven:** cannot detect "nothing happened" cases such as a stalled verification.
- **Pure scheduled polling:** progress would lag after approvals; wasteful if run too often.
- **Message queue/worker service:** more robust at scale but heavier than a bounded student project needs. Noted as a possible future improvement.

## Duplicate Prevention

Each notification stores a unique key (`type + student_id + reference_id + date`). A second attempt with the same key is skipped, so a job that runs twice does not send duplicates.

## Manual Run

A coordinator-only endpoint (`POST /api/jobs/run`) triggers the daily job on demand, which makes the behavior easy to demonstrate and test.
