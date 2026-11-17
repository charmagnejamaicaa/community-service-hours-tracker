# Week 15 — Hardening Report (IA6)

**Project 20: Community Service Hours Tracker**
**Author:** Charmagne Jamaica Marcos (24-10337, BIST 3A)

## Summary

After the baseline integration test run (all 38 Postman requests across 5 folders passed — see `27-postman-ratelimit.png` and the full Week 14 test suite), the code was reviewed specifically for weaknesses that functional testing alone does not catch: abuse scenarios, stale-privilege windows, and unbounded resource use. Three genuine weaknesses were found, each mapped to an OWASP API Security Top 10 risk, fixed, and proven with a new automated regression test. The full backend test suite grew from 81 to 84 tests, all passing, and the Postman collection was re-run in full afterward with no regressions (`28-postman-regression.png`).

## Weaknesses found and fixed

### 1. No rate limit on authenticated routes — OWASP API4:2023 (Unrestricted Resource Consumption)

**Before:** only `POST /auth/login` was rate-limited (5/min). Every other endpoint — reading progress, registering activities, submitting hours, approving submissions — had no limit once a user held a valid token. A compromised token, a buggy client, or a malicious script could call the API without restriction.

**Fix:** added a second, more permissive rate limiter (`RateLimiter::for('api', ...)` in `AppServiceProvider`, 60 requests/minute per signed-in user) applied via `throttle:api` middleware to the entire authenticated route group in `routes/api.php`. This is separate from the stricter `login` limiter, which still only guards sign-in.

**Files changed:** `app/Providers/AppServiceProvider.php`, `routes/api.php`

**Proof:** `tests/Feature/AuthorizationTest.php::test_a_general_rate_limit_applies_to_authenticated_routes` — sends 60 requests to `/auth/me`, confirms all succeed, then confirms the 61st returns `429 RATE_LIMITED` with a `Retry-After` header.

### 2. Tokens not revoked when a user's role or student_id changes — OWASP API5:2023 (Broken Function Level Authorization)

**Before:** if a user's `role` or `student_id` were ever updated (e.g. by a future admin feature, or directly in the database), their existing tokens kept working with the OLD role/identity for up to `TOKEN_MINUTES` (60 minutes). This was already flagged as an accepted residual risk in Week 14's `security-note.md`.

**Fix:** added `app/Observers/UserObserver.php`, registered in `AppServiceProvider::boot()` via `User::observe(UserObserver::class)`. It hooks into the model's `updated` event and deletes all of a user's Sanctum tokens whenever `role` or `student_id` changes — forcing a fresh sign-in that reflects the new identity immediately, regardless of which code path performed the update.

**Files changed:** `app/Observers/UserObserver.php` (new), `app/Providers/AppServiceProvider.php`

**Proof:** `tests/Feature/AuthenticationTest.php::test_changing_a_users_role_revokes_their_existing_tokens` — logs in, confirms the token works, changes the user's role directly, then confirms the same old token now gets `401 UNAUTHENTICATED`.

### 3. Unbounded result set in `listSubmissions()` — OWASP API4:2023 (Unrestricted Resource Consumption)

**Before:** `DbWorkflowRepository::listSubmissions()` had no `LIMIT` clause. With a handful of demo records this was invisible, but at real scale (thousands of submissions) a single request could force the database and API to load and serialize the entire table, slowing or crashing the server — and the problem gets worse over time as data grows, with no attacker needed.

**Fix:** added `limit`/`offset` parameters to `listSubmissions()`, hard-capped at 100 regardless of what's passed in (defense in depth for any future caller), and added query-string validation in `WorkflowController::listSubmissions()` (`?limit=&offset=`, default limit 50, rejecting anything outside 1–100 with `400 VALIDATION_ERROR`).

**Files changed:** `app/Repositories/DbWorkflowRepository.php`, `app/Http/Controllers/WorkflowController.php`

**Proof:** `tests/Feature/AuthorizationTest.php::test_listing_submissions_is_paginated_and_capped` — seeds extra submissions, confirms `limit=2` returns exactly 2 items with the correct `limit`/`offset` echoed back, and confirms `limit=500` is rejected with `400 VALIDATION_ERROR`.

## Why these three and not others

Candidates were drawn from an explicit code review against the OWASP API Security Top 10, not from Postman test failures (the Week 14 functionality was already correct for everything the baseline Postman run checked). All three are things that only show up under abuse, scale, or administrative change — exactly the blind spot integration testing alone misses, which is the point of IA6.

## Regression proof

- Backend: `php artisan test` → **84 passed (322 assertions)**, up from 81/247 before this week's fixes — no existing test broke.
- Postman: full 5-folder, 38-request collection re-run after the fixes on a freshly reseeded database (`28-postman-regression.png`) — all passed, no regressions.
- See also `traceability-matrix.md` (rows REQ-SEC-2, REQ-SEC-3, REQ-NF-3) and `failure-injection.md`.
