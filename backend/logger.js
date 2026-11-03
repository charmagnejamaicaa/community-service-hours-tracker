'use strict';

/**
 * Structured JSON logger: one line per event.
 * Do NOT log passwords, tokens, or unnecessary personal data. Prefer IDs over names.
 */
function log(level, event, fields = {}) {
  const line = { ts: new Date().toISOString(), level, event, ...fields };
  const out = JSON.stringify(line);
  if (level === 'ERROR') {
    console.error(out);
  } else {
    console.log(out);
  }
}

module.exports = {
  info: (event, fields) => log('INFO', event, fields),
  warn: (event, fields) => log('WARN', event, fields),
  error: (event, fields) => log('ERROR', event, fields),
};
