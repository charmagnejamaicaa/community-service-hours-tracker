'use strict';

/**
 * Same two demos as notificationDemo.js, but the notifications are SAVED IN MYSQL,
 * so they appear in GET /api/notifications/failed and survive a restart.
 *
 * Run with:  node demoToDatabase.js
 * (needs MySQL running, schema.sql + schema_notifications.sql loaded, and a .env file)
 *
 * Each run uses a new reference id, so running it again creates new rows (not duplicates).
 */

require('dotenv').config();

const logger = require('./logger');
const { getPool } = require('./db');
const { sendNotification } = require('./notificationService');
const { createFakeProvider } = require('./notificationProvider');
const { createMysqlStore } = require('./notificationStoreMysql');

const SHORT_DELAYS = [500, 1000]; // milliseconds, demo only (the real policy is 1 and 5 minutes)

async function main() {
  const store = createMysqlStore(getPool);
  const run = Date.now();

  console.log('\n=== DEMO 1: transient failure that recovers on its own ===\n');
  const recovered = await sendNotification(
    { type: 'DEADLINE_REMINDER', studentId: 1, referenceId: `deadline-${run}`, message: 'Deadline soon' },
    { provider: createFakeProvider('timeout_once'), store, logger, maxAttempts: 3, delaysMs: SHORT_DELAYS }
  );
  console.log(`\nResult: status=${recovered.status}, attempts=${recovered.attempts}`);

  console.log('\n=== DEMO 2: permanent failure routed to a human ===\n');
  const failed = await sendNotification(
    { type: 'MISSING_VERIFICATION_ALERT', studentId: 2, referenceId: `submission-${run}`, message: 'Please verify' },
    { provider: createFakeProvider('always_down'), store, logger, maxAttempts: 3, delaysMs: SHORT_DELAYS }
  );
  console.log(`\nResult: status=${failed.status}, attempts=${failed.attempts}, reason=${failed.lastError}`);

  console.log('\nSaved in MySQL. Open http://localhost:3000/api/notifications/failed (with npm start running).\n');
  await getPool().end();
}

main().catch((err) => {
  console.error('Demo crashed:', err.message);
  process.exit(1);
});
