# Requirements-to-Test Traceability Matrix (Week 15, IA6)

**Project 20: Community Service Hours Tracker**

Each row links a requirement (from Week 14's security note or the Week 13 workflow contract) to the Postman request that tests it, the test type, and the expected result. Request numbers match `week15-collection.json`.

| Req ID | Requirement | Test type | Postman request | Expected result | Status |
| --- | --- | --- | --- | --- | --- |
| REQ-AUTH-1 | Valid email+password returns a token | Positive | 1.1-1.5 | 200, token + correct role | Pass |
| REQ-AUTH-2 | Wrong password / unknown email refused, same message | Negative | 1.6, 1.7 | 401 INVALID_CREDENTIALS, identical message | Pass |
| REQ-VAL-1 | Malformed/missing login fields rejected | Boundary/Negative | 1.8, 1.9 | 400 VALIDATION_ERROR with details | Pass |
| REQ-AUTHN-1 | No token on a protected route | Unauthorized | 1.10 | 401 UNAUTHENTICATED, has referenceId | Pass |
| REQ-AUTHN-2 | Altered token rejected | Unauthorized | 1.11 | 401 UNAUTHENTICATED | Pass |
| REQ-AUTHN-3 | Revoked (signed-out) token rejected | Unauthorized | 1.12, 1.13 | 401 after logout | Pass |
| REQ-AUTHZ-1 | Student reads own progress | Positive | 2.1 | 200, matches own ID | Pass |
| REQ-AUTHZ-2 | Student cannot read another student's progress | Forbidden | 2.2 | 403 FORBIDDEN | Pass |
| REQ-NF-1 | Progress for a non-existent student ID | Not found | 2.3 | 404 NOT_FOUND | Pass |
| REQ-AUTHZ-3 | Supervisor cannot read the progress summary | Forbidden | 2.4 | 403 FORBIDDEN | Pass |
| REQ-AUTHZ-4 | Adviser (read-only) CAN read the summary | Positive | 2.5 | 200 | Pass |
| REQ-AUTHZ-5 | Adviser cannot approve (read-only enforced on writes) | Forbidden | 2.6 | 403 FORBIDDEN | Pass |
| REQ-WF-1 | Student registers an activity | Positive | 3.1 | 201 Created | Pass |
| REQ-VAL-2 | Activity missing a required field | Negative | 3.2 | 400 VALIDATION_ERROR | Pass |
| REQ-WF-2 | Student submits valid hours | Positive | 3.3 | 201 Created | Pass |
| REQ-VAL-3 | Hours above the 12-hour cap (boundary) | Boundary | 3.4 | 400 VALIDATION_ERROR | Pass |
| REQ-NF-2 | Submit against a non-existent activity ID | Not found | 3.5 | 404 NOT_FOUND | Pass |
| REQ-WF-3 | Supervisor lists submitted items | Positive | 3.6 | 200 | Pass |
| REQ-WF-4 | Supervisor verifies a submission | Positive | 3.7 | 200, status VERIFIED | Pass |
| REQ-WF-5 | Verifying the same submission twice | Duplicate | 3.8 | 409 INVALID_STATE | Pass |
| REQ-AUTHZ-6 | Student cannot approve (wrong role) | Forbidden | 3.9 | 403 FORBIDDEN | Pass |
| REQ-WF-6 | Coordinator approves a verified submission | Positive | 3.10 | 200, status APPROVED | Pass |
| REQ-WF-7 | Approving an already-approved submission twice | Duplicate | 3.11 | 409 (no double-count) | Pass |
| REQ-WF-8 | Resubmit a submission that is not REJECTED | Invalid state | 3.12 | 409 INVALID_STATE | Pass |
| REQ-REL-1 | API health check | Availability | 4.1 | 200 | Pass |
| REQ-REL-2 | Plain HTTP request to the API | Dependency/config failure | 4.2 | 426 HTTPS_REQUIRED | Pass |
| REQ-REL-3 | Coordinator runs the daily job on demand | Positive | 4.3 | 200 | Pass |
| REQ-REL-4 | Two overlapping job runs | Duplicate/concurrency | 4.4 | 409 JOB_RUNNING (or 200 if 4.3 finished first) | Pass |
| REQ-REL-5 | Permanently-failed notification reaches manual review | Dependency failure (timeout/always-down, injected) | 4.5 | 200, item present | Pass |
| REQ-REL-6 | Coordinator closes a manual-review item | Positive | 4.6 | 200 | Pass |
| REQ-SEC-1 | Repeated wrong logins trigger the rate limit | Security/DoS hardening | 5.1-5.6 (Runner, 6x) | 429 RATE_LIMITED with Retry-After | Pass |
| REQ-SEC-2 | Signed-in users hitting the API too fast on ANY route (not just login) get rate-limited | Security/DoS hardening (Week 15 fix #1) | PHPUnit: `test_a_general_rate_limit_applies_to_authenticated_routes` | 429 RATE_LIMITED with Retry-After after 60 req/min | Pass |
| REQ-SEC-3 | A user's existing tokens are revoked the moment their role or student_id changes | Security hardening (Week 15 fix #2) | PHPUnit: `test_changing_a_users_role_revokes_their_existing_tokens` | Old token gets 401 UNAUTHENTICATED right after the change | Pass |
| REQ-NF-3 | Submission listing is paginated and capped, never returns an unbounded result set | Reliability/resource-exhaustion hardening (Week 15 fix #3) | PHPUnit: `test_listing_submissions_is_paginated_and_capped` | `limit`/`offset` respected; `limit` over 100 → 400 VALIDATION_ERROR | Pass |

**Owner:** Charmagne Jamaica Marcos. **Exit criterion:** every row above shows "Pass" after the Week 15 hardening fixes (section "Weaknesses found and fixed" in `hardening-report.md`), confirmed by re-running the full Postman collection (see `28-postman-regression.png`) and the full backend test suite (84 passed).
