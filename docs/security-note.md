# Security Note (Week 14)

**Project 20 - Community Service Hours Tracker** | SA8 / IA5 Week 14
Stack: Flutter Web, Laravel 12 REST API (Sanctum tokens), MySQL, local HTTPS gateway (Caddy).

All data is fictional. Demo passwords and secrets live only in `.env`, which is never committed.

## 1. What is protected

| Asset | Why it matters | Where it lives |
| --- | --- | --- |
| Account passwords | Anyone with a password can act as that person | `users.password` (bcrypt hash only) |
| Sign-in tokens | A token is a password for one hour | `personal_access_tokens.token` (SHA-256 hash only); the plain token is held in Flutter memory |
| Service records (hours, evidence notes, approvals) | Wrong or leaked records harm students' standing | MySQL tables, reached only through the API |
| Student identity (name, student ID) | Personal data | MySQL; returned only to the owner and to staff roles |
| Logs | Can leak data if careless | `storage/logs/structured.log` |

## 2. Controls, where they are, and how they are shown

| Requirement | What was done | Where | Proof |
| --- | --- | --- | --- |
| Authentication | Email + password login returns a Sanctum Bearer token that expires after `TOKEN_MINUTES` (60). Logout revokes it on the server. A new login replaces the old token. | `AuthController`, `config/service_hours.php`, `config/sanctum.php` | `AuthenticationTest`; demo steps 1, 2, 10 |
| Role-based authorization | Every route except `/health` and `/auth/login` needs a valid token. `role:` middleware checks the role stored in the database (not a header the client can change). | `routes/api.php`, `RequireRole` | `AuthorizationTest`; demo steps 6 to 8 |
| Ownership checks | A student can read and change only their own progress, activities and submissions. Checked in the controller on top of the role. | `Access`, `ApiController`, `WorkflowController` | `AccessTest`, `AuthorizationTest`; demo step 6 |
| HTTPS | The API refuses plain HTTP with `426 HTTPS_REQUIRED` when `REQUIRE_HTTPS=true`. Locally, Caddy provides `https://localhost:8443` with a trusted local certificate. Only loopback may say "this was HTTPS". | `RequireHttps`, `bootstrap/app.php`, Caddy | `AuthorizationTest`; demo step 11 |
| Protected configuration | Secrets and settings come from `.env` (ignored by Git). `.env.example` has placeholders only. The seeder refuses to run without `DEMO_USER_PASSWORD`. The Flutter app contains no secrets. | `.env.example`, `DemoUserSeeder`, `.gitignore` | `git status` shows no `.env`; seeder error when the password is missing |
| Input validation | Login input and every workflow input are checked on the server (type, length, range, date rules, allowed states). Bad input gives `400 VALIDATION_ERROR` listing the problems. The app also checks before sending. | `AuthController`, `HoursRules`, `WorkflowController` | `HoursRulesTest`, `AuthenticationTest`; demo step 3 |
| Safe error handling | One error format. Unexpected errors return a generic message and a reference ID. Details go only to the log. | `ApiExceptionRenderer`, `ApiError` | `AuthenticationTest` (error format) |
| Safe logging | Logs hold IDs, roles, codes and reference IDs. Values of keys such as `password`, `token`, `authorization`, `email`, `secret`, `cookie` are replaced with `[redacted]` even if a developer logs them by mistake. A failed login logs no email. | `StructuredLog::redact`, `AuthController` | `StructuredLogTest`; read `structured.log` after the demo |
| Brute-force limit | 5 sign-in requests per minute for one email and address (successful ones count too), 30 per minute per address, then `429 RATE_LIMITED` with `Retry-After`. | `AppServiceProvider` | `AuthenticationTest`; demo step 12 |
| Safe browser defaults | `nosniff`, `X-Frame-Options: DENY`, `Referrer-Policy: no-referrer`, a `default-src 'none'` Content-Security-Policy, `Cache-Control: no-store`, and a CORS allow-list for local origins only. HSTS can be switched on with `ENABLE_HSTS=true` (off on localhost, see section 6). | `SecurityHeaders`, `config/cors.php` | `AuthorizationTest`; header listing in the evidence |
| Data minimization | The login answer returns only `id`, `name`, `role`, `studentId`. Email and password hash are never returned. Notification and log records use IDs. Supervisors see submissions, not student contact data. | `AuthController`, serializers | Read the login response |

## 3. Who can do what

| Action | Student | Supervisor | Coordinator | Adviser |
| --- | --- | --- | --- | --- |
| Register activity, submit, resubmit | own only | no | no | no |
| View a student's progress | own only | no | any | any (read only) |
| Progress summary (all students) | no | no | yes | yes |
| List submissions | no | yes | yes | no |
| Verify or reject | no | yes | no | no |
| Approve, return for correction | no | no | yes | no |
| Failed notifications, run daily job | no | no | yes | no |

The Flutter app hides what a role cannot use, but hiding is only a convenience. The server refuses the request on its own.

## 4. Cryptography decision record

Cryptography is used only where it solves a named problem.

| Tool | Used? | Reason |
| --- | --- | --- |
| Password hashing (bcrypt, via Laravel `hashed` cast) | Yes | A stolen database must not reveal passwords. bcrypt is slow on purpose and salted. Passwords are never encrypted, because nobody needs to read them back. |
| Hashing of tokens (SHA-256, by Sanctum) | Yes | The token is shown once at login. The database keeps only its hash, so a database leak does not give attackers usable tokens. |
| Encryption in transit (TLS through Caddy) | Yes | Passwords and tokens must not cross the network in plain text. Plain HTTP is refused. |
| Encryption of evidence notes in the database | No | The notes are short fictional text and no files are stored. The database is reached only through the API, and key management (storing, rotating and backing up a key) would add more risk than it removes at this stage. Revisit if real documents or photos are stored. |
| Message authentication codes (HMAC) or signatures | No | Tokens are looked up on the server, so they need no signature, and this allows instant revocation at logout. Notifications go to a fake provider, so there is no external message to sign. A signed, stateless token (JWT) was rejected because it cannot be revoked before it expires. |

## 5. Adverse behavior shown

| Case | Expected result | How to show it |
| --- | --- | --- |
| Wrong password | `401 INVALID_CREDENTIALS`, same text as an unknown email | Flutter login, or demo steps 1 and 2 |
| No token | `401 UNAUTHENTICATED` | Demo step 4 |
| Altered token | `401 UNAUTHENTICATED` | Demo step 5 |
| Expired token | `401 UNAUTHENTICATED` | Demo step 9 (the token expiry is set in the past) and `AuthenticationTest` |
| Revoked token (after logout) | `401 UNAUTHENTICATED` | Demo step 10 |
| Student reads another student | `403 FORBIDDEN` | Demo step 6 |
| Wrong role (student approves, adviser approves, supervisor reads summary) | `403 FORBIDDEN` | Demo steps 7 and 8 |
| Invalid input | `400 VALIDATION_ERROR` with the list of problems | Demo step 3 |
| Plain HTTP | `426 HTTPS_REQUIRED` | Demo step 11 |
| Too many logins | `429 RATE_LIMITED` | Demo step 12 |

## 6. Residual risk (what is still open)

| Risk | Impact | Why it is accepted for now | Next step |
| --- | --- | --- | --- |
| The token is kept in browser memory only | A page refresh signs the user out. It cannot be stolen from browser storage, but a script injected into the page could still read it. | Safer than `localStorage`; no third-party scripts are loaded; the API sends a strict CSP. | Consider an HttpOnly cookie flow if the app is deployed. |
| No multi-factor sign-in, no password reset, no account lockout beyond the rate limit | A guessed or reused password gives access. | Demo scope; accounts are fictional. | Add MFA and a reset flow before real use. |
| A supervisor can see all submissions, not only those from their own partner organization | Over-broad access for supervisors. | The data model has no supervisor-to-organization link yet. | Add that link and filter the list. |
| The demo password is shared by all demo accounts | One leaked password opens every demo role. | Local, fictional data only. | Use a different password per account, or invitation links. |
| The local database user is `root` with no password (XAMPP default) | Anyone on this computer can open the database directly. | Development machine only. | Create a limited database user and a password for any shared or hosted setup. |
| Evidence notes are stored as plain text | Anyone with database access can read them. | See the decision record: no files, fictional text. | Encrypt the column or use disk encryption if real evidence is stored. |
| The local HTTPS certificate is issued by a local authority installed on this computer | It is trusted only here. It is not valid for other computers. | Needed for local HTTPS. Remove it with `caddy untrust`. | Use a public certificate (for example Let's Encrypt) when deployed. |
| HSTS is off on localhost | The browser is not told to always use HTTPS. | HSTS applies to every port of `localhost` and would break the Flutter dev server. The API already refuses HTTP. | Turn on `ENABLE_HSTS=true` in production. |
| The API trusts forwarded headers from loopback | A local program on the same computer could fake "HTTPS". | Only local users can reach loopback. | In deployment, trust only the real proxy address. |
| One-hour tokens are not revoked when a user's role changes | A demoted user keeps the old role until the token expires or they sign out. | Roles are read from the database on every request, so the effect is limited. | Delete the user's tokens when a role is changed. |
| Rate limiting uses the file cache | The counters are not shared between servers. | One server only. | Use Redis or the database when deployed. |
| `APP_DEBUG=true` in local `.env` | Debug detail may appear in the log. | Local only; API responses never include it. | Set `APP_DEBUG=false` and `APP_ENV=production` when deployed. |
