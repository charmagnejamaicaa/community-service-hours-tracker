# Workflow Boundary

**Project 20 - Community Service Hours Tracker** | SA7 Week 13

## Purpose

Track a student's community service hours from activity registration to approved completion, with automation that keeps progress visible and unverified records from going stale.

## Actors and Authority

| Actor | Role in workflow | Authority |
| --- | --- | --- |
| Student | Registers activity, submits attendance/evidence | Submit only; cannot approve own hours |
| Partner Supervisor | Verifies that the student served the hours | Verify or reject submissions |
| Coordinator | Approves hours, handles corrections, resolves failed notifications | Final approval decision |
| Adviser | Views student progress and summaries | Read-only |

## In Scope

1. Activity registration
2. Attendance or evidence submission
3. Supervisor verification
4. Coordinator hour approval
5. Correction of rejected or incorrect entries
6. Completion when approved hours reach the requirement
7. Automation: remaining-hour calculation, deadline reminders, missing-verification alerts, progress summaries
8. Manual-review queue for permanently failed notifications

## Out of Scope (for this project)

- Real payment, grading, or transcript integration
- Real SMS/email providers in production (a simulated or sandbox provider is used)
- Real student or partner-organization data (controlled/fictional data only)
- Mobile apps and offline mode
- Authentication and role security (added in Week 14)

## Workflow States

`REGISTERED -> SUBMITTED -> VERIFIED -> APPROVED -> COMPLETED`
Side paths: `SUBMITTED -> REJECTED -> (correction) -> SUBMITTED`

## Paths to Demonstrate

- **Normal path:** register -> submit -> verify -> approve -> remaining hours updated -> completed.
- **Invalid path:** negative hours, hours over the daily limit, missing evidence, past-dated entry beyond the allowed window.
- **Unauthorized path:** a student attempts to verify or approve (role check stubbed in Week 13, enforced in Week 14).
- **Unavailable-service path:** the notification provider is down.
- **Recovery path:** retries succeed after a transient failure; permanent failures go to manual review.

## Assumptions

- Required hours default is 40 (configurable via `REQUIRED_HOURS`). Change this to your school's requirement.
- Each student has one required-hours target and one completion deadline.
