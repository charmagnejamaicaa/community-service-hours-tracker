# Maintainability Note

**Project 20 - Community Service Hours Tracker** | SA7 Week 13

## Structure

- **Separation of concerns:** routes/controllers handle HTTP, services hold business rules (hour calculation, state transitions), and a separate notification module handles sending and retries.
- **Automation isolated from the API:** scheduled jobs call the same service functions as the API, so the logic exists in one place.
- **Frontend layers:** UI widgets, state management, and an API client class are kept apart so endpoint changes only touch the client.

## Configuration

All tunable values (required hours, reminder days, retry limit, provider URL) live in environment variables, documented in `.env.example`. Changing the policy does not require code changes.

## Readability and Consistency

- Consistent naming for states (`SUBMITTED`, `VERIFIED`, `APPROVED`, ...) shared by the API and the UI
- Structured JSON logs with a stable `event` name for each automation action
- Small functions with one responsibility, and comments only where the reason is not obvious

## Testability

- The remaining-hours calculation is a pure function and is easy to unit test.
- The notification provider sits behind an interface, so a fake provider can simulate timeouts and rejections for the required demonstrations.

## Known Risks (Week 13)

- Scheduler runs inside the app process; if the app is down at 08:00, the job runs at the next start (catch-up check needed).
- No authentication yet (planned for Week 14).

## Future Improvements

- Move notifications to a queue/worker if volume grows
- Add dashboards for log monitoring (Week 16)
