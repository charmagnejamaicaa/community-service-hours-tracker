# Week 15 — Failure Injection Demo

This demonstrates that the notification pipeline handles provider failures correctly: a **transient** failure recovers automatically, and a **permanent** failure is routed to manual review instead of being silently lost or retried forever.

## Command

```
php artisan app:demo-failures
```

This command deliberately simulates two failure scenarios against the notification provider (no real email/SMS is sent — see `FakeProvider`).

## Scenario 1: transient failure that recovers

The provider times out on the first attempt, then succeeds on the second (within the configured retry policy).

```json
{"ts":"2026-11-17T06:14:52Z","level":"WARN","event":"notification_attempt_failed","notificationId":1,"type":"DEADLINE_REMINDER","attempt":1,"maxAttempts":3,"reason":"PROVIDER_TIMEOUT","nextAttemptAt":"2026-11-17 14:14:53"}
{"ts":"2026-11-17T06:14:53Z","level":"INFO","event":"notification_sent","notificationId":1,"type":"DEADLINE_REMINDER","attempt":2}
```

**Result:** `outcome: SENT, attempts: 2, status: SENT`

**What this proves:** a single provider hiccup does not fail the notification outright — the retry policy (bounded attempts, logged reason) gives it a second chance, and it is marked `SENT` once it succeeds.

## Scenario 2: permanent failure routed to manual review

The provider rejects the request outright (e.g. invalid recipient) — a failure that retrying would not fix.

```json
{"ts":"2026-11-17T06:14:53Z","level":"ERROR","event":"notification_routed_manual_review","notificationId":2,"type":"MISSING_VERIFICATION_ALERT","attempts":1,"reason":"PROVIDER_REJECTED","permanent":true}
```

**Result:** `outcome: MANUAL_REVIEW, attempts: 1, status: MANUAL_REVIEW, reason: PROVIDER_REJECTED`

**What this proves:** the system recognizes failures that retrying cannot fix, and instead of looping forever or silently dropping the notification, it surfaces the item for a human (coordinator) to review — confirmed by Postman request **4.5 List failed notifications**, which found it:

```json
{
  "count": 1,
  "items": [
    {
      "id": 2,
      "type": "MISSING_VERIFICATION_ALERT",
      "studentId": 2,
      "referenceId": "demo-permanent-061452",
      "attempts": 1,
      "reason": "PROVIDER_REJECTED",
      "status": "MANUAL_REVIEW",
      "createdAt": "2026-11-17T14:14:53.000Z"
    }
  ]
}
```

A coordinator can then resolve it via **PATCH /notifications/{id}/handled** (Postman request 4.6), confirmed working in the regression run.

## Evidence files

- `27-postman-ratelimit.png` — rate-limit baseline proof
- `28-postman-regression.png` — full regression run after the 3 weakness fixes
- This document, plus the raw terminal output above
