# API Contract (Draft for Week 13)

**Project 20 - Community Service Hours Tracker** | SA7 Week 13

Base path: `/api` | Format: JSON | Dates: ISO 8601
Authentication and role enforcement are added in Week 14. In Week 13, the role is passed in a demo header (`X-Demo-Role`) for testing only.

## Endpoints

| Method | Path | Role | Description |
| --- | --- | --- | --- |
| POST | `/activities` | Student | Register an activity |
| POST | `/activities/{id}/submissions` | Student | Submit hours and evidence |
| GET | `/submissions?status=SUBMITTED` | Supervisor | List submissions awaiting verification |
| PATCH | `/submissions/{id}/verify` | Supervisor | Verify or reject (with reason) |
| PATCH | `/submissions/{id}/approve` | Coordinator | Approve verified hours |
| PATCH | `/submissions/{id}/correct` | Coordinator | Return a submission for correction |
| GET | `/students/{id}/progress` | Student, Coordinator, Adviser | Required, approved, pending, remaining hours |
| GET | `/summaries/progress` | Coordinator, Adviser | Progress summary for all students |
| GET | `/notifications/failed` | Coordinator | Manual-review queue |
| PATCH | `/notifications/{id}/handled` | Coordinator | Mark a failed notification as handled |
| POST | `/jobs/run` | Coordinator | Run the daily automation on demand |
| GET | `/health` | Public | Health check |

## Example: Submit Hours

`POST /api/activities/12/submissions`

```json
{
  "hours": 4.5,
  "serviceDate": "2026-10-25",
  "evidenceNote": "Tree planting at barangay site, signed attendance sheet photo"
}
```

Response `201 Created`:

```json
{
  "id": 87,
  "status": "SUBMITTED",
  "hours": 4.5,
  "createdAt": "2026-10-29T12:00:00Z"
}
```

## Example: Progress

`GET /api/students/5/progress`

```json
{
  "studentId": 5,
  "requiredHours": 40,
  "approvedHours": 22.5,
  "pendingHours": 4.5,
  "remainingHours": 17.5,
  "deadline": "2026-12-15",
  "status": "IN_PROGRESS"
}
```

`remainingHours = max(requiredHours - approvedHours, 0)` (only approved hours count).

## Validation Rules

- `hours`: number greater than 0 and at most 12 per day
- `serviceDate`: not in the future; not older than 30 days
- `evidenceNote`: required, maximum 500 characters
- Only a `SUBMITTED` entry can be verified; only a `VERIFIED` entry can be approved

## Error Format

```json
{
  "error": {
    "code": "VALIDATION_ERROR",
    "message": "Hours must be greater than 0 and at most 12.",
    "referenceId": "req-20261029-0042"
  }
}
```

Status codes: `400` validation, `401/403` authentication/authorization (Week 14), `404` not found, `409` invalid state transition, `503` dependency unavailable, `500` server error.
