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

Done:
- Workflow boundary, trigger decision, screen flow, API contract, retry and recovery policy, and maintainability note are in `docs/`.
- Backend: submission workflow (SUBMITTED, VERIFIED, APPROVED, REJECTED) with role checks, validation and safe concurrent updates.
- Daily job (node-cron, 08:00 Asia/Manila): deadline reminders, verification alerts, progress summary.
- Notifications: bounded retries (3 attempts, 1 min then 5 min), one recovered transient failure, one permanent failure routed to the manual review queue, structured JSON logs.
- Flutter Web: dashboard, register activity, submit hours, verification queue, approval, progress summary, review queue, role switcher.
- Tests: 80 backend tests and 25 Flutter widget tests, all passing.

Not done yet (later weeks):
- Real sign-in and role security (Week 14). The `X-Demo-Role` header is a temporary stand-in.
- Full test plan and HTTPS (Weeks 14 and 15).

Screenshots are in `docs/screenshots/`.

How to run: see the steps in `backend/` and `frontend/`. Start MySQL, then `npm start` in `backend`, then `flutter run -d chrome` in `frontend`.