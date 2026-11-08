# Community Service Hours Tracker

**Course:** PFEBSIT 002 Integrative Programming and Technologies 2
**Project:** Project 20 - Community Service Hours Tracker
**Student:** Charmagne Jamaica Marcos
**ID:** 24-10337
**Section:** BIST 3A
**Institution:** University of La Salette, Inc. - College of Information Technology

## Overview

Students need accurate progress records for their required community service hours. This system lets students register service activities and submit attendance or evidence, lets partner supervisors verify the hours, and lets coordinators approve, correct, and complete the records. Automation keeps everyone informed: it calculates remaining hours, sends deadline reminders, alerts on missing verifications, and produces progress summaries.

**Users:** students, coordinators, partner supervisors, advisers.

**Workflow:** activity registration -> attendance/evidence submission -> supervisor verification -> hour approval -> correction -> completion.

## Tech Stack

- **Frontend:** Flutter Web
- **Backend:** Laravel 12 (PHP 8.2) REST API
- **Database:** MySQL (XAMPP)
- **Scheduler:** Laravel scheduler (`php artisan schedule:work`), daily at 08:00 Asia/Manila
- **Tests:** PHPUnit (`php artisan test`) and Flutter widget tests (`flutter test`)

## Versions

| Version | Week | What it contains |
| --- | --- | --- |
| v1.0.0 | 13 | Automation foundation and reliable workflow (SA7) |

## Project Status (Weeks 13-18)

| Week | Assessment | Status |
| --- | --- | --- |
| 13 | SA7 - Automation case and reliable workflow | Done (v1.0.0) |
| 14 | SA8 / IA5 - Secure integration | Not started |
| 15 | IA6 - Testing and hardening | Not started |
| 16 | Performance and deployment evaluation | Not started |
| 17 | SA9 / AS3 - UAT and technical report | Not started |
| 18 | E3 - Final demo and defense | Not started |

## Week 13 (SA7) Evidence

All documents are in the [`docs/`](docs/) folder:

- [Workflow boundary](docs/workflow-boundary.md)
- [Trigger decision](docs/trigger-decision.md)
- [Flutter screen flow](docs/screen-flow.md)
- [API contract](docs/api-contract.md)
- [Retry and recovery policy](docs/retry-recovery-policy.md)
- [Maintainability note](docs/maintainability-note.md)
- [Week 13 summary](docs/week-13-summary.md) (what was built, problems met, what is left)

Other evidence:

- **Structured logs:** JSON lines with IDs and codes only, written to `backend-laravel/storage/logs/structured.log` and to the terminal.
- **One recovered transient failure and one permanent failure sent to manual review:** run `php artisan app:demo-failures`. The permanent failure appears in `GET /api/notifications/failed` and in the Review queue screen of the Flutter app.
- **Screenshots:** [`docs/screenshots/`](docs/screenshots/)

### What is built

- **Workflow API:** register activity, submit hours, verify or reject, approve, return for correction, resubmit. Each step is checked against the workflow rules, and concurrent changes are safe (the second user gets a 409 conflict).
- **Automation:** remaining-hour calculation on approval (event-driven); deadline reminders, missing-verification alerts, and progress summaries in a daily job (scheduled).
- **Reliability:** notifications use bounded retries (3 attempts, 1 minute then 5 minutes). Temporary failures are retried and recover; permanent failures go to a manual review queue, where a coordinator marks them handled. A unique key prevents duplicate notifications.
- **Flutter Web screens:** dashboard, register activity, submit hours, verification queue, approval, progress summary, and review queue, with a role switcher (student, supervisor, coordinator, adviser). Each screen handles loading, empty, error with retry, and success states.
- **Consistent errors:** every error uses `{ error: { code, message, details?, referenceId } }`, and the reference ID also appears in the logs.

## Repository Layout

```
/backend-laravel   Laravel REST API (current backend)
/backend           Earlier Node.js/Express prototype (kept for history; replaced by backend-laravel)
/frontend          Flutter Web source
/docs              Documentation and evidence (including docs/screenshots)
```

## How to Run

Requirements: PHP 8.2+, Composer, MySQL (XAMPP), Flutter SDK, Chrome.

1. **Database:** start MySQL in XAMPP. In phpMyAdmin, run `backend/schema.sql` and then `backend/schema_notifications.sql` (this creates the `service_hours` database with fictional sample data).
2. **Backend:**
   ```
   cd backend-laravel
   composer install
   copy .env.example .env
   php artisan key:generate
   ```
   In `.env`, set:
   ```
   DB_CONNECTION=mysql
   DB_HOST=127.0.0.1
   DB_PORT=3306
   DB_DATABASE=service_hours
   DB_USERNAME=root
   DB_PASSWORD=
   SESSION_DRIVER=file
   CACHE_STORE=file
   QUEUE_CONNECTION=sync
   ```
   Then start the API:
   ```
   php artisan serve --port=3000
   ```
   Check `http://localhost:3000/api/health`.
3. **Frontend:**
   ```
   cd frontend
   flutter pub get
   flutter run -d chrome
   ```
4. **Tests:**
   ```
   cd backend-laravel
   php artisan test
   cd ../frontend
   flutter test
   ```
5. **Demos:**
   ```
   cd backend-laravel
   php artisan app:demo-failures
   php artisan app:daily-job
   ```
6. **Scheduler (optional):** `php artisan schedule:work` keeps the daily 08:00 job running.

Optional `.env` settings (defaults already exist): `JOB_TIMEZONE`, `REMINDER_DAYS_BEFORE_DEADLINE`, `VERIFICATION_ALERT_DAYS`, `RETRY_MAX_ATTEMPTS`, `RETRY_DELAYS_MS`, `NOTIFICATION_MODE` (`ok`, `timeout_once`, `always_down`, `reject`).

## Data and Security Notes

- Only fictional, anonymized, or instructor-approved data is used.
- No real credentials or secrets are committed. `.env` is ignored by Git; use `.env.example` for placeholders.
- Logs contain IDs and codes only, not names or message text.
- The notification provider is a fake one that simulates failures. No real email or SMS is sent.

## Known Limitations

- **No authentication yet.** The `X-Demo-Role` header is a temporary stand-in for the user's role. Real sign-in, role enforcement, and HTTPS are planned for Week 14. Until then, the progress, summary, and notification endpoints do not check roles.
- The daily job runs only while the scheduler process is running (`php artisan schedule:work`). If it is not running at 08:00, that day's job is missed until the next run. Running it twice on the same day does not send duplicates.
- Notifications use a fake provider, so the retry and recovery behavior is demonstrated but not connected to a real messaging service.
- Backend tests cover the business rules (hours, workflow, retries, daily job). Tests against the database and the full API are planned for Week 15.
- The Flutter app has widget tests with a fake API; full end-to-end tests are planned for Week 15.