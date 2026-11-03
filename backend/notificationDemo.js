'use strict';

/**
 * Demo for your teacher: shows the two required Week 13 failure cases.
 * Run with:  node notificationDemo.js
 * Take a screenshot of the output as evidence.
 *
 * Waits are shortened to a fraction of a second so the demo is quick.
 * In real use the policy waits are 1 minute and 5 minutes (see notificationService.js).
 */

const logger = require('./logger');
const { sendNotification } = require('./notificationService');
const { createFakeProvider } = require('./notificationProvider');
const { createMemoryStore } = require('./notificationStore');

const SHORT_DELAYS = [500, 1000]; // milliseconds, demo only

async function main() {
  const store = createMemoryStore();

  console.log('\n=== DEMO 1: transient failure that recovers on its own ===');
  console.log('The provider times out on attempt 1, then works on attempt 2.\n');
  const recovered = await sendNotification(
    { type: 'DEADLINE_REMINDER', studentId: 1, referenceId: 'deadline-2026-12-15', message: 'Deadline soon' },
    { provider: createFakeProvider('timeout_once'), store, logger, maxAttempts: 3, delaysMs: SHORT_DELAYS }
  );
  console.log(`\nResult: status=${recovered.status}, attempts=${recovered.attempts}`);

  console.log('\n=== DEMO 2: permanent failure routed to a human ===');
  console.log('The provider is down on every attempt. After 3 attempts it goes to manual review.\n');
  const failed = await sendNotification(
    { type: 'MISSING_VERIFICATION_ALERT', studentId: 2, referenceId: 'submission-4', message: 'Please verify' },
    { provider: createFakeProvider('always_down'), store, logger, maxAttempts: 3, delaysMs: SHORT_DELAYS }
  );
  console.log(`\nResult: status=${failed.status}, attempts=${failed.attempts}, reason=${failed.lastError}`);

  console.log('\n=== MANUAL REVIEW QUEUE (what the coordinator would see) ===');
  const queue = await store.listByStatus('MANUAL_REVIEW');
  console.table(
    queue.map((n) => ({ id: n.id, type: n.type, studentId: n.studentId, attempts: n.attempts, reason: n.lastError }))
  );
}

main().catch((err) => {
  console.error('Demo crashed:', err);
  process.exit(1);
});
