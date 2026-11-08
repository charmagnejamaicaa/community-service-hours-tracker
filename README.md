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
- **Backend:** Laravel 12 (PHP 8.2) REST API with Laravel Sanctum token sign-in
- **Database:** MySQL (XAMPP)
- **HTTPS (local):** Caddy gateway, `https://localhost:8443` to the API on port 3000
- **Scheduler:** Laravel scheduler (`php artisan schedule:work`), daily at 08:00 Asia/Manila
- **Tests:** PHPUnit (`php artisan test`) and Flutter widget tests (`flutter test`)

## Versions

| Version | Week | What it contains |
| --- | --- | --- |
| v1.0.0 | 13 | Automation foundation and reliable workflow (SA7) |
| v2.0.0 | 14 | Secure integration: sign-in, roles, HTTPS, safe logging (SA8 / IA5) |

## Project Status (Weeks 13-18)

| Week | Assessment | Status |
| --- | --- | --- |
| 13 | SA7 - Automation case and reliable workflow | Done (v1.0.0) |
| 14 | SA8 / IA5 - Secure integration | Done (v2.0.0) |
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
- **Flutter Web screens:** login, dashboard, register activity, submit hours, verification queue, approval, progress summary, review queue and account, shown according to the signed-in role (student, supervisor, coordinator, adviser). Each screen handles loading, empty, error with retry, and success states.
- **Consistent errors:** every error uses `{ error: { code, message, details?, referenceId } }`, and the reference ID also appears in the logs.

## Week 14 (SA8 / IA5) Evidence

- [Security note](docs/security-note.md): controls, role table, cryptography decision record, adverse behavior, residual risk
- [API contract](docs/api-contract.md): now with sign-in, roles and the security headers
- [Week 14 summary](docs/week-14-summary.md): what was built, problems met, what is left
- **Adverse behavior demo:** `docs/security-demo.ps1` shows wrong password, no token, altered token, expired token, revoked token, wrong role, another student's data, invalid input, plain HTTP and too many sign-ins, each with its status code.
- **Tests:** `php artisan test` (81 backend tests, including feature tests for sign-in, roles, ownership and HTTPS) and `flutter test` (42 tests).
- **Screenshots:** [`docs/screenshots/`](docs/screenshots/) (files 12 and up are Week 14)

### What changed in Week 14

- **Sign-in:** email and password return a token that expires after 60 minutes. The Flutter app opens on a login screen; the role comes from the server. Sign out revokes the token.
- **Role and ownership checks on every endpoint.** Students see only their own data. The Week 13 `X-Demo-Role` header is gone.
- **HTTPS:** the API refuses plain HTTP (`426`). Locally, Caddy provides `https://localhost:8443`.
- **Safer logging and errors:** passwords, tokens and emails are redacted from logs; failed logins log no email; unexpected errors show only a reference ID.
- **Login limit:** 5 attempts per minute, then `429`.

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
   DEMO_USER_PASSWORD=choose-a-long-password-here
   REQUIRE_HTTPS=true
   ```
   `DEMO_USER_PASSWORD` is the password for all fictional demo accounts (at least 12 characters). It stays in `.env` only. Then create the sign-in tables and the demo accounts:
   ```
   php artisan migrate
   php artisan db:seed
   ```
   Demo accounts (fictional): `student1@example.test`, `student2@example.test`, `supervisor@example.test`, `coordinator@example.test`, `adviser@example.test`.

   Start the API:
   ```
   php artisan serve --port=3000
   ```
   Start the HTTPS gateway in a second terminal (install once with `winget install --id CaddyServer.Caddy -e`, then `caddy trust` while it runs):
   ```
   caddy reverse-proxy --from localhost:8443 --to 127.0.0.1:3000
   ```
   Check `https://localhost:8443/api/health`. Plain `http://localhost:3000/api/...` is refused on purpose.
3. **Frontend:**
   ```
   cd frontend
   flutter pub get
   flutter run -d chrome
   ```
   The app opens on a sign-in page and talks to `https://localhost:8443`. To use another address: `flutter run -d chrome --dart-define=API_BASE_URL=https://your-host`.
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
- Logs contain IDs and codes only. Passwords, tokens and emails are redacted.
- Passwords are stored as bcrypt hashes and tokens as SHA-256 hashes. The demo password is in `.env` only.
- The notification provider is a fake one that simulates failures. No real email or SMS is sent.

## Known Limitations

- The full residual risk list is in [docs/security-note.md](docs/security-note.md). The main points: no multi-factor sign-in or password reset; supervisors can see all submissions; one shared demo password; the local database user is `root` without a password; evidence notes are stored as plain text; HSTS is off on localhost.
- The sign-in token is kept in browser memory only, so refreshing the page signs the user out.
- The local HTTPS certificate is trusted only on this computer (remove it with `caddy untrust`).
- The daily job runs only while the scheduler process is running (`php artisan schedule:work`). If it is not running at 08:00, that day's job is missed until the next run. Running it twice on the same day does not send duplicates.
- Notifications use a fake provider, so the retry and recovery behavior is demonstrated but not connected to a real messaging service.
- Flutter has widget tests with a fake API; full end-to-end tests and more backend database tests are planned for Week 15.
