# Week 14 Summary: Secure Integration (SA8 / IA5)

**Project 20: Community Service Hours Tracker**
Course: PFEBSIT 002 Integrative Programming and Technologies 2
University of La Salette, Inc., College of IT
Stack: Flutter Web (frontend), Laravel 12 with Sanctum (backend), MySQL (database), Caddy (local HTTPS)

## 1. Goal this week

Make the Week 13 workflow safe to use: real sign-in, role-based access, HTTPS, protected configuration, input validation, safe logging and data minimization. Use cryptography only where it is justified, show what happens when a token is altered, expired or invalid or when a user is not allowed, and write down the residual risk.

## 2. Week 14 evidence checklist

| Required evidence | Where to find it |
| --- | --- |
| Authentication | `AuthController`, `POST /api/auth/login`; Flutter login screen; screenshots 12 to 15 (wrong password, student dashboard, account, login page after sign-out) |
| Role-based authorization | `routes/api.php`, `RequireRole`, `Access`; role table in `docs/security-note.md`; screenshot 16 (supervisor sees two tabs) |
| HTTPS | Caddy gateway at `https://localhost:8443`; plain HTTP refused with 426 (demo step 11); screenshots 21 (HTTP refused), 22 (security headers) and 23 (app over HTTPS) |
| Protected configuration | `.env` (ignored by Git), `.env.example` placeholders, `config/service_hours.php` |
| Input validation | Server rules in `AuthController`, `HoursRules`, `WorkflowController`; demo step 3 |
| Safe logging | `StructuredLog::redact`; `storage/logs/structured.log` |
| Data minimization | Login returns only id, name, role, studentId; logs hold IDs only |
| Cryptography only when justified | Decision record in `docs/security-note.md` (section 4) |
| Altered, expired, invalid or unauthorized behavior | `docs/security-demo.ps1`; screenshots 19, 20 and 24 to 26; `AuthenticationTest`, `AuthorizationTest` |
| Residual risk | `docs/security-note.md` (section 6) |
| Updated API contract | `docs/api-contract.md` |

## 3. What was built

### Backend (Laravel 12)

- **Sign-in:** `POST /api/auth/login` checks the email and password (bcrypt) and returns a Sanctum token that expires after 60 minutes. A dummy hash is checked when the email is unknown, so the answer and the timing do not show which accounts exist. A new sign-in replaces the old token. `POST /api/auth/logout` revokes the token.
- **Roles:** student, supervisor, coordinator and adviser (read only). Every route needs a token, and the `role:` middleware checks the role stored in the database.
- **Ownership:** a student can only read their own progress and only register, submit and resubmit their own records.
- **HTTPS:** with `REQUIRE_HTTPS=true` the API answers plain HTTP with 426. Only loopback may report "this was HTTPS" through the proxy header.
- **Errors and logs:** one error format with a reference ID; 401, 403, 429 and 426 added; unexpected errors never show details. Sensitive keys are redacted from logs and a failed login logs no email.
- **Brute-force limit:** 5 sign-in requests per minute for one email and address.
- **Security headers:** `nosniff`, `X-Frame-Options`, `Referrer-Policy`, Content-Security-Policy, `Cache-Control: no-store`; CORS limited to localhost origins; HSTS available with `ENABLE_HSTS=true`.

### Frontend (Flutter Web)

- **Login screen** instead of the role dropdown. The role comes from the login answer.
- **Token handling:** the token is kept in memory only and sent as `Authorization: Bearer`. A 401 sends the user back to the login screen with a message. A 429 shows the "too many attempts" message.
- **Role-based tabs:** every role has at least two tabs, including an **Account** tab with a Sign out button. Students see their own dashboard without typing an ID.
- The app uses `https://localhost:8443` by default (`--dart-define=API_BASE_URL=...` to change it).

## 4. Testing

- **Backend:** 81 tests (`php artisan test`): unit tests for the rules, plus feature tests that call the real API against a throw-away in-memory database for sign-in, tokens, roles, ownership, rate limit, security headers and HTTPS.
- **Frontend:** 42 Flutter tests (`flutter test`), including sign-in, role-based screens, sign-out, expired session and token handling in the API client.
- **Live demo:** `docs/security-demo.ps1` runs each adverse case against the running system.

## 5. Problems met and how they were fixed

| Problem | Cause | Fix |
| --- | --- | --- |
| A request without a token returned 500 instead of 401 | Laravel tried to redirect to a `login` page that this API does not have, before my error handler could answer | `redirectGuestsTo(fn () => null)` and always render `/api/*` errors as JSON |
| `X-Reference-Id` header missing on 401 | The sign-in check runs before the middleware that adds the header | The header is now set where every error response is built |
| A stray migration and three stray files appeared (`create_users_table`, `Pseudonym.php`, `config/security.php`, `DemoUsersSeeder.php`) | A second, parallel version of the same work was pasted into the project | Checked that nothing used them, then deleted them before committing |
| HTTPS through the gateway was refused (426) | The HTTPS check ran before Laravel read the proxy header; then the proxy setting was read from `.env` before `.env` was loaded | Run the check later, and trust the proxy header from loopback only, without reading `.env` |
| `code bootstrap\app.php` opened an empty file | The terminal was in the repo root, so a new file was created in the wrong folder | Closed it without saving and opened `backend-laravel\bootstrap\app.php` |
| HSTS could break the Flutter dev server | Browsers apply HSTS to every port of `localhost` | HSTS is now opt-in with `ENABLE_HSTS=true` and off locally |

## 6. What I learned

- Hiding a button is not security: the server must refuse the request itself, and a test must prove it.
- Give the same answer for a wrong password and an unknown email, so nobody can find out which accounts exist.
- Store only hashes of passwords and tokens, so a leaked database does not leak access.
- Cryptography should solve a named problem. Writing down why something is not used (encrypting notes, signing tokens) is part of the design.
- Configuration is read at different moments (`bootstrap/app.php` runs before `.env` is loaded), so order matters.
- A residual risk list makes it clear what is safe for a demo and what must change before real use.

## 7. Not done yet (planned for later weeks)

- Week 15: full test plan, more database and end-to-end tests, hardening of the open risks that are cheap to fix (per-account demo passwords, token cleanup on role change).
- Week 16: performance and deployment (public HTTPS certificate, production settings, limited database user).
- Weeks 17 and 18: user acceptance testing, report and final defense.

## 8. How to run it

See the README ("How to Run"). In short: start MySQL, run `php artisan migrate` and `php artisan db:seed`, start the API (`php artisan serve --port=3000`) and the gateway (`caddy reverse-proxy --from localhost:8443 --to 127.0.0.1:3000`), then `flutter run -d chrome` and sign in with one of the demo accounts.
