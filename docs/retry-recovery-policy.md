# Retry and Recovery Policy

**Project 20 - Community Service Hours Tracker** | SA7 Week 13

## Scope

Applies to automated notifications (deadline reminders, missing-verification alerts, progress summaries) sent through the notification provider.

## Failure Classification

| Type | Examples | Action |
| --- | --- | --- |
| Transient | Timeout, HTTP 503, network drop | Retry with backoff |
| Permanent | Invalid recipient, HTTP 4xx from the provider, retries exhausted | Stop retrying; route to manual review |

## Retry Rules (bounded)

- Maximum attempts: **3** (`RETRY_MAX_ATTEMPTS`)
- Wait before retry 2: 1 minute; before retry 3: 5 minutes (increasing backoff)
- Each attempt is recorded with a timestamp and result
- No retries after the maximum, so there is no infinite loop

## Notification Status Lifecycle

`PENDING -> SENT`
`PENDING -> RETRYING -> SENT` (recovered transient failure)
`PENDING -> RETRYING -> FAILED_PERMANENT -> MANUAL_REVIEW -> HANDLED`

## Recovery

- **Idempotency:** a unique key (`type + student_id + reference_id + date`) prevents duplicate notifications if a job runs twice or restarts.
- **Restart safety:** on startup, notifications in `RETRYING` whose next-attempt time has passed are picked up again.
- **Manual handling:** permanently failed notifications appear in the coordinator's Manual Review Queue (Screen 8) with the reason and the number of attempts. The coordinator contacts the student another way and marks the item as handled.
- **Business data is never lost:** a failed notification never changes or blocks hour records.

## Required Demonstrations

1. **Recovered transient failure:** simulate the provider timing out on attempt 1, succeeding on attempt 2. Evidence: logs showing `attempt=1 failed`, `attempt=2 sent`, final status `SENT`.
2. **Permanent failure routed to a human:** simulate the provider rejecting every attempt (or an invalid recipient). Evidence: logs showing 3 failed attempts, status `MANUAL_REVIEW`, and the item visible in the Manual Review Queue.

## Structured Log Format (JSON, one line per event)

```json
{"ts":"2026-10-29T08:00:03Z","level":"WARN","event":"notification_attempt_failed","notificationId":311,"type":"DEADLINE_REMINDER","attempt":1,"maxAttempts":3,"reason":"PROVIDER_TIMEOUT","nextAttemptAt":"2026-10-29T08:01:03Z"}
{"ts":"2026-10-29T08:01:04Z","level":"INFO","event":"notification_sent","notificationId":311,"type":"DEADLINE_REMINDER","attempt":2}
{"ts":"2026-10-29T08:05:10Z","level":"ERROR","event":"notification_routed_manual_review","notificationId":312,"type":"MISSING_VERIFICATION_ALERT","attempts":3,"reason":"PROVIDER_REJECTED"}
```

Logs must not contain passwords, tokens, or unnecessary personal data. Use IDs instead of names where possible.
