'use strict';

const mysql = require('mysql2/promise');

let pool = null;

/**
 * Lazily creates one shared connection pool.
 * Reads DATABASE_URL from .env, for example:
 *   DATABASE_URL=mysql://root:@localhost:3306/service_hours
 * (format: mysql://USER:PASSWORD@HOST:PORT/DATABASE)
 */
function getPool() {
  if (!pool) {
    const url = process.env.DATABASE_URL;
    if (!url || url === 'replace_me') {
      throw new Error('DATABASE_URL is not set. Copy .env.example to .env and fill it in.');
    }
    pool = mysql.createPool({
      uri: url,
      waitForConnections: true,
      connectionLimit: 5,
      decimalNumbers: true, // return DECIMAL columns as numbers, not strings
      // Read and write dates as UTC. Without this, a DATE column such as 2026-12-15 is read as
      // local midnight and can shift by one day for computers that are not on UTC.
      timezone: 'Z',
    });
  }
  return pool;
}

module.exports = { getPool };