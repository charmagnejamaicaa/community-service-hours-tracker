# API Contract (Week 13)

**Project 20 - Community Service Hours Tracker** | SA7 Week 13

Base path: `/api` | Format: JSON | Dates: `YYYY-MM-DD` (dates) or ISO 8601 (timestamps)

**Roles (Week 13 stand-in):** the caller's role is sent in the header `X-Demo-Role` with the value `student`, `supervisor`, or `coordinator`. This is for testing only. Real authentication and role enforcement replace it in Week 14.

## Endpoints

| Method | Path | Role | Description | Status |
| --- | --- | --- | --- | --- |
| POST | `/activities` | student | Register an activity | Built |
| POST | `/activities/{id}/submissions` | student | Submit hours and evidence | Built |
| GET | `/submissions?status=SUBMITTED` | supervisor, coordinator | List submissions by status | Built |
| PATCH | `/submissions/{id}/verify` | supervisor | Verify or reject (reason required to reject) | Built |
| PATCH | `/submissions/{id}/approve` | coordinator | Approve verified hours; returns remaining hours | Built |
| PATCH | `/submissions/{id}/correct` | coordinator | Return a verified submission for correction (reason required) | Built |
| PATCH | `/submissions/{id}/resubmit` | student | Send a corrected submission again | Built |
| GET | `/students/{id}/progress` | student, coordinator, adviser | Required, approved, pending, remaining hours | Built |
| GET | `/summaries/progress` | coordinator, adviser | Progress summary for all students | Built |
| GET | `/notifications/failed` | coordinator | Manual review queue | Built |
| PATCH | `/notifications/{id}/handled` | coordinator | Mark a failed notification as handled | Built |
| POST | `/jobs/run` | coordinator | Run the daily automation on demand | Built |
| GET | `/health` | public | Health check | Built |

`/students/{id}/progress`, `/summaries/progress`, and `/notifications/*` do not check the demo role yet (Week 14).

## Workflow states

```
SUBMITTED --verify--> VERIFIED --approve--> APPROVED   (final)
SUBMITTED --reject--> REJECTED
VERIFIED  --correct--> REJECTED
REJECTED  --resubmit--> SUBMITTED
```

Only `APPROVED` hours count toward the requirement. Any other change returns `409 INVALID_STATE`.
Two people acting on the same submission at the same moment: only the first succeeds, the second gets `409`.

## Examples

### Register an activity
`POST /api/activities` (role: student)
```json
{ "studentId": 1, "name": "Tree Planting", "organization": "Sample Barangay Council", "activityDate": "2026-11-03" }
```
Response `201 Created`:
```json
{ "id": 12, "studentId": 1, "name": "Tree Planting", "organization": "Sample Barangay Council", "activityDate": "2026-11-03" }
```

### Submit hours
`POST /api/activities/12/submissions` (role: student)
```json
{ "hours": 4.5, "serviceDate": "2026-11-03", "evidenceNote": "Signed attendance sheet photo" }
```
Response `201 Created`:
```json
{ "id": 87, "status": "SUBMITTED", "hours": 4.5, "createdAt": "2026-11-04T04:00:00.000Z" }
```

### Verify or reject
`PATCH /api/submissions/87/verify` (role: supervisor)
```json
{ "decision": "VERIFY" }
```
```json
{ "decision": "REJECT", "reason": "Photo is unreadable" }
```
Response `200`: `{ "id": 87, "status": "VERIFIED" }` (or `"REJECTED"`)

### Approve
`PATCH /api/submissions/87/approve` (role: coordinator)

Response `200`. The remaining hours are recalculated right away (event-driven trigger):
```json
{ "id": 87, "status": "APPROVED", "studentId": 1, "remainingHours": 35.5, "progressStatus": "IN_PROGRESS" }
```
`progressStatus` is `IN_PROGRESS`, `COMPLETED`, or `OVERDUE`.

### Return for correction, then resubmit
`PATCH /api/submissions/87/correct` (role: coordinator): `{ "reason": "Hours differ from the attendance sheet" }` -> `{ "id": 87, "status": "REJECTED" }`

`PATCH /api/submissions/87/resubmit` (role: student): `{ "hours": 3.5, "serviceDate": "2026-11-03", "evidenceNote": "New clear photo" }` -> `{ "id": 87, "status": "SUBMITTED" }`

### Progress
`GET /api/students/1/progress`
```json
{
  "studentId": 1,
  "requiredHours": 40,
  "approvedHours": 22.5,
  "pendingHours": 4.5,
  "remainingHours": 17.5,
  "percentComplete": 56.25,
  "daysLeft": 41,
  "deadline": "2026-12-15",
  "status": "IN_PROGRESS"
}
```
`remainingHours = max(requiredHours - approvedHours, 0)` (only approved hours count).

### Run the daily job
`POST /api/jobs/run` (role: coordinator) returns, for example:
```json
{
  "ranAt": "2026-11-04T00:00:00.000Z",
  "deadlineReminders": { "considered": 1, "sent": 1, "manualReview": 0, "duplicates": 0, "errors": 0 },
  "verificationAlerts": { "considered": 1, "sent": 1, "manualReview": 0, "duplicates": 0, "errors": 0 },
  "summary": { "students": 2, "completed": 0, "inProgress": 1, "overdue": 1, "approvedHours": 52.5, "remainingHours": 27.5 }
}
```
A second call while the job is running returns `409 JOB_RUNNING`.

## Validation rules

- `hours`: a number greater than 0 and at most 12
- Total hours for one student on one date: at most 12 (rejected submissions do not count)
- `serviceDate`: not in the future; not older than 30 days
- `evidenceNote`: required, at most 500 characters
- Activity `name` and `organization`: required, at most 150 characters each
- `reason` (reject or correct): required, at most 255 characters
- Only a `SUBMITTED` entry can be verified or rejected; only a `VERIFIED` entry can be approved or returned; only a `REJECTED` entry can be resubmitted

## Error format

```json
{
  "error": {
    "code": "VALIDATION_ERROR",
    "message": "Hours must be greater than 0 and at most 12.",
    "details": ["Hours must be greater than 0 and at most 12."],
    "referenceId": "req-1a2b3c4d"
  }
}
```
`details` appears on validation errors and lists every problem found. `referenceId` matches the same value in the server logs.

| Status | Code | Meaning |
| --- | --- | --- |
| 400 | `VALIDATION_ERROR` | Invalid input |
| 403 | `FORBIDDEN` | The role is not allowed to do this (Week 13 demo header) |
| 404 | `NOT_FOUND` | Student, activity, submission, or notification does not exist |
| 409 | `INVALID_STATE` | The workflow does not allow this change right now |
| 409 | `JOB_RUNNING` | The daily job is already running |
| 500 | `SERVER_ERROR` | Unexpected error (details are only in the server log) |