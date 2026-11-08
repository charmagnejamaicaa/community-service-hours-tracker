# service_hours_tracker

A new Flutter project.

## Getting Started

This project is a starting point for a Flutter application.

A few resources to get you started if this is your first Flutter project:

- [Learn Flutter](https://docs.flutter.dev/get-started/learn-flutter)
- [Write your first Flutter app](https://docs.flutter.dev/get-started/codelab)
- [Flutter learning resources](https://docs.flutter.dev/reference/learning-resources)

For help getting started with Flutter development, view the
[online documentation](https://docs.flutter.dev/), which offers tutorials,
samples, guidance on mobile development, and a full API reference.
## Week 13 status (SA7: automation case and reliable workflow)

**Stack:** Flutter Web (frontend), Laravel 12 + PHP 8.2 (REST API), MySQL (database).

Done:
- Workflow boundary, trigger decision, screen flow, API contract, retry and recovery policy, and maintainability note are in `docs/`.
- Backend (Laravel): submission workflow (SUBMITTED, VERIFIED, APPROVED, REJECTED) with role checks, validation and safe concurrent updates.
- Daily job (Laravel scheduler, 08:00 Asia/Manila): deadline reminders, verification alerts, progress summary.
- Notifications: bounded retries (3 attempts, 1 min then 5 min), one recovered transient failure, one permanent failure routed to the manual review queue, structured JSON logs.
- Flutter Web: dashboard, register activity, submit hours, verification queue, approval, progress summary, review queue, role switcher.
- Tests: 38 Laravel unit tests (`php artisan test`) and 25 Flutter widget tests (`flutter test`).

Not done yet (later weeks):
- Real sign-in and role security (Week 14). The `X-Demo-Role` header is a temporary stand-in.
- Full test plan, performance and deployment evaluation (Weeks 15 and 16).

Screenshots are in `docs/screenshots/`.

### How to run
1. Start MySQL (XAMPP) and import `schema.sql` and `schema_notifications.sql` (database `service_hours`).
2. Backend: `cd backend-laravel`, copy `.env.example` to `.env`, set the `DB_*` values (MySQL, database `service_hours`), then `composer install`, `php artisan key:generate`, `php artisan serve --port=3000`.
3. Frontend: `cd frontend`, `flutter pub get`, `flutter run -d chrome`.
4. Tests: `php artisan test` (backend-laravel) and `flutter test` (frontend).
5. Demos: `php artisan app:demo-failures` and `php artisan app:daily-job`.
6. Scheduler (optional): `php artisan schedule:work`.

### Versions
| Version | Week | What it contains |
|---|---|---|
| v1.0.0 | 13 | Automation foundation, reliable workflow |