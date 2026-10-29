# Flutter Web Screen Flow

**Project 20 - Community Service Hours Tracker** | SA7 Week 13

## Screens

| # | Screen | Role | Purpose |
| --- | --- | --- | --- |
| 1 | Sign-in (stub in Week 13) | All | Select a demo role; real authentication comes in Week 14 |
| 2 | Student Dashboard | Student | Required, approved, pending, and remaining hours; deadline; recent notifications |
| 3 | Register Activity | Student | Enter activity name, partner organization, date |
| 4 | Submit Hours / Evidence | Student | Enter hours and attach evidence or attendance note |
| 5 | Verification Queue | Supervisor | List submissions awaiting verification; verify or reject with reason |
| 6 | Approval and Correction | Coordinator | Approve verified hours; return an entry for correction |
| 7 | Progress Summary | Coordinator / Adviser | Per-student progress and weekly summary |
| 8 | Manual Review Queue | Coordinator | Notifications that failed permanently; mark as handled |

## Navigation Flow

```
Sign-in -> Student Dashboard -> Register Activity -> Submit Hours / Evidence -> Dashboard (status: SUBMITTED)

Sign-in -> Verification Queue -> Verify / Reject -> (Verified) -> Approval and Correction
                                                              -> Approve -> Dashboard updates remaining hours
                                                              -> Return for correction -> Student resubmits

Sign-in -> Progress Summary
Sign-in -> Manual Review Queue -> Mark handled
```

## Required UI States (per screen where applicable)

- **Loading:** spinner while waiting for the API
- **Empty:** "No activities yet", "Nothing to verify"
- **Validation error:** inline messages (for example, hours must be greater than 0 and no more than the daily limit)
- **Success:** confirmation after a submit, verify, or approve action
- **Unauthorized:** message when the role is not allowed to perform an action
- **Network error:** retry button when the API is unreachable
- **Server error:** friendly message with a reference ID from the logs

## Responsive Layout

- Mobile width: single column, bottom or drawer navigation
- Tablet/desktop: dashboard cards in a grid, side navigation, tables for queues

## Wireframes

_Add sketches or screenshots here (hand drawings, Figma, or app screenshots all work)._
