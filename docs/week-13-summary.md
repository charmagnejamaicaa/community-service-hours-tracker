# Week 13 Summary: Automation Case and Reliable Workflow (SA7)

**Project 20: Community Service Hours Tracker**
Course: PFEBSIT 002 Integrative Programming and Technologies 2
University of La Salette, Inc., College of IT
Stack: Flutter Web (frontend), Laravel 12 on PHP 8.2 (REST API), MySQL (database)

## 1. Goal this week

Show a working automation case with a reliable workflow: what starts it, what the system does, how it fails, how it recovers, and what a person does when it cannot recover.

## 2. Week 13 evidence checklist

| Required evidence | Where to find it |
|---|---|
| Workflow boundary | `docs/workflow-boundary.md` |
| Trigger decision | `docs/trigger-decision.md` |
| Flutter Web screen flow | `docs/screen-flow.md` and the Flutter screens in `frontend/lib` |
| API contract | `docs/api-contract.md` and `backend-laravel/routes/api.php` |
| Retry and recovery policy | `docs/retry-recovery-policy.md` |
| Structured logs | `backend-laravel/app/Support/StructuredLog.php` (JSON lines with IDs and codes only, no personal data); output in `storage/logs/structured.log` |
| One recovered transient failure | `php artisan app:demo-failures` (attempt 1 times out, attempt 2 succeeds) |
| One permanent failure sent to manual review | Same demo; the item appears in `GET /api/notifications/failed` and the Flutter Review queue |
| Maintainability note | `docs/maintainability-note.md` |
| Screenshots | `docs/screenshots/` |

## 3. What was built

### Backend (Laravel 12 + MySQL)

- **Hours rules** (`app/Support/HoursRules.php`): hours above 0 and at most 12; no more than 12 hours per student per day; the service date cannot be in the future or older than 30 days; an evidence note is required (up to 500 characters). It also calculates remaining hours and progress status (IN_PROGRESS, COMPLETED, OVERDUE).
- **Submission workflow** (`app/Support/Workflow.php`, `WorkflowController`): SUBMITTED, then VERIFIED (supervisor), then APPROVED (coordinator). A submission can be rejected or returned for correction, and the student can resubmit. Updates use `UPDATE ... WHERE id = ? AND status = ?`, so two people cannot act on the same submission at once (the second gets a 409 conflict).
- **Roles:** a temporary `X-Demo-Role` header checked by the `DemoRole` middleware. Real sign-in is planned for Week 14.
- **Notifications with retries** (`NotificationService`): up to 3 attempts, with a 1 minute and then 5 minute wait. Temporary failures are retried. Permanent failures go to MANUAL_REVIEW and are later marked HANDLED. An idempotency key (`type:studentId:referenceId:date`) prevents duplicate messages.
- **Daily job** (`DailyJob`, Laravel scheduler): runs at 08:00 Asia/Manila. It sends deadline reminders and verification alerts and builds a progress summary. A cache lock stops two runs at the same time, and a coordinator can start it by hand.
- **Structured logs:** one JSON line per event, with IDs and codes only.
- **Consistent errors:** `{ error: { code, message, details?, referenceId } }` with codes VALIDATION_ERROR, FORBIDDEN, NOT_FOUND, INVALID_STATE, JOB_RUNNING and SERVER_ERROR.

### Frontend (Flutter Web)

- **Role switcher** at the top right: Student, Supervisor, Coordinator, Adviser. Each role sees its own screens.
- **Student:** Dashboard, Register activity, Submit hours (including Resubmit).
- **Supervisor:** Verification queue (Verify or Reject with a reason).
- **Coordinator:** Approval (Approve or Return for correction), Review queue (failed notifications), Progress summary.
- **Adviser:** Dashboard and Progress summary (read only).
- Every screen handles loading, empty, error with Retry, and success states. Server errors show a reference ID that matches the backend logs.
- Responsive layout: side navigation on wide screens, bottom navigation on narrow screens.

## 4. Testing

- **Backend:** 38 unit tests with PHPUnit (`php artisan test`) for the hours rules, workflow, retry policy, daily job and summary.
- **Frontend:** 25 Flutter widget tests (`flutter test`), all passing. They cover loading, empty, error and retry, validation, the approve, verify and reject flows, conflict handling, and role switching.
- **Demos:** `php artisan app:demo-failures` and `php artisan app:daily-job` show the retry, recovery and manual review behavior from the command line.

## 5. Change of backend framework

I first built the backend with Node.js and Express (about 80 tests, kept in the repository history). The subject uses Laravel, so I rebuilt it in Laravel 12. The design stayed the same (rules, states, retry policy, API contract and error format), so the Flutter app needed no change. This was possible because the rules were kept separate from the web framework.

## 6. Problems met and how they were fixed

| Problem | Cause | Fix |
|---|---|---|
| `MODULE_NOT_FOUND` on `hoursService` when running tests (Express version) | The file was missing and there were two similar test files | Added the correct file and deleted the duplicate |
| Dates were one day off (Express version) | MySQL returned dates as local midnight in UTC+8 | Set the connection time zone and formatted dates as `YYYY-MM-DD` |
| Stray text "Main · DART" on line 1 of a Dart file | Text copied from the chat card | Deleted the line; copied from the downloaded files instead |
| `flutter test` stuck with a Windows policy error | Windows Security blocked Flutter's compiler | Added Windows Security exclusions for the Flutter folder and the project |
| `FormErrorBox` / `SuccessBox` not defined | The new `widgets.dart` was not pasted fully | Pasted the full file again |
| 3 failing Flutter tests | Two tests matched text exactly but the error box adds a "- " prefix; the Dashboard status row overflowed on narrow screens | Tests match part of the text; the row text is wrapped in `Expanded` |
| `php artisan db:show` failed with a missing `performance_schema` table | XAMPP's MariaDB does not have that monitoring table | Used `php artisan db:table students` instead; the connection itself worked |
| `intl` extension warning from `php artisan db:table` | The extension is off in `php.ini` | Enabled `extension=intl` (optional, it only affects that command) |

## 7. What I learned

- A workflow is reliable when every failure has a defined next step: retry, then a person.
- Separating temporary failures from permanent ones avoids useless retries and hides nothing from staff.
- Guarding a state change in the database (`WHERE status = ?`) is simpler and safer than trying to prevent clashes in the app.
- Keeping business rules out of the framework made the move from Express to Laravel much faster.
- Logging IDs and codes instead of messages keeps personal data out of the logs.

## 8. Not done yet (planned for later weeks)

- Week 14: real sign-in, role security and HTTPS (the demo role header is only a stand-in; progress, summary and notification endpoints do not check roles yet).
- Week 15: full test plan and testing, including Laravel feature tests against the database.
- Weeks 16 to 18: performance and deployment evaluation, UAT, final demonstration.

## 9. How to run it

1. Start MySQL (XAMPP) and import `schema.sql` and `schema_notifications.sql`.
2. `cd backend-laravel`, then `composer install`, `php artisan serve --port=3000`.
3. `cd frontend`, then `flutter pub get`, `flutter run -d chrome`.
4. Tests: `php artisan test` in `backend-laravel`, `flutter test` in `frontend`.

All data in the project is fictional. Secrets (`.env`) and `vendor` are never committed.