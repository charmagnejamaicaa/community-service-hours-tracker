# Maintainability Note

**Project 20 - Community Service Hours Tracker** | SA7 Week 13

**Stack:** Flutter Web (frontend), Laravel 12 on PHP 8.2 (REST API), MySQL (database).

## Structure

- **Separation of concerns:** routes and controllers (`routes/api.php`, `app/Http/Controllers`) handle HTTP only. Business rules live in plain PHP classes in `app/Support` (`HoursRules`, `Workflow`) and `app/Services` (`NotificationService`, `DailyJob`). Database queries live in `app/Repositories`.
- **Automation isolated from the API:** the daily job (`DailyJob`) uses the same rule classes as the API, so each rule exists in one place. The scheduler, the `php artisan app:daily-job` command and `POST /api/jobs/run` all call the same `JobRunner`.
- **Interfaces for replaceable parts:** `NotificationProvider`, `NotificationStore`, `JobRepository` and `EventLogger` are interfaces in `app/Contracts`. They are bound in `AppServiceProvider`, so a part can be swapped (for example the fake notification provider for a real one) without touching the rules.
- **Frontend layers:** UI widgets, state in each screen, and one API client class (`api_client.dart`) are kept apart, so endpoint changes only touch the client.

## Configuration

All tunable values live in `.env` and are read in one file, `config/service_hours.php`: reminder days (`REMINDER_DAYS_BEFORE_DEADLINE`), verification alert days (`VERIFICATION_ALERT_DAYS`), retry limit and waits (`RETRY_MAX_ATTEMPTS`, `RETRY_DELAYS_MS`), provider mode (`NOTIFICATION_MODE`), job time and time zone. Changing the policy does not require code changes. `.env` is never committed.

## Readability and Consistency

- Consistent state names (`SUBMITTED`, `VERIFIED`, `APPROVED`, `REJECTED`) shared by the API and the UI.
- One error format on every endpoint: `{ error: { code, message, details?, referenceId } }`. The `referenceId` also appears in the logs.
- Structured JSON logs (`storage/logs/structured.log`) with a stable `event` name for each automation action. IDs and codes only, no personal data.
- Small classes with one responsibility, and comments only where the reason is not obvious.

## Testability

- Hours rules, workflow rules, retry policy and the daily job are plain PHP classes with no database or HTTP, so they are unit tested directly (`php artisan test`, 38 unit tests).
- The notification provider sits behind an interface, so a fake provider simulates timeouts and rejections for the required demonstrations (`php artisan app:demo-failures`).
- Flutter has 25 widget tests (`flutter test`) with a fake API, so no server is needed.

## Known Risks (Week 13)

- The scheduler needs a running process (`php artisan schedule:work`, or a Windows Task Scheduler entry that runs `php artisan schedule:run` every minute). If it is not running at 08:00, that day's job is missed until the next run. The overlap guard and the notification key (which includes the date) make a late or repeated run safe.
- No authentication yet: the `X-Demo-Role` header is a stand-in (real sign-in and role enforcement are planned for Week 14). Progress, summary and notification endpoints do not check roles yet.

## Future Improvements

- Move notifications to a Laravel queue and worker if volume grows.
- Add dashboards for log monitoring (Week 16).
- Add Laravel feature tests against the database (Week 15).