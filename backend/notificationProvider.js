'use strict';

/**
 * A FAKE notification provider used to simulate real-world failures.
 * (No real email/SMS is sent. Real providers are out of scope for this project.)
 *
 * Every provider exposes: send(notification) -> Promise
 * On failure it throws a ProviderError with:
 *   - reason:    short code for logs (for example PROVIDER_TIMEOUT)
 *   - transient: true  = temporary problem, worth retrying
 *                false = permanent problem, retrying will not help
 */
class ProviderError extends Error {
  constructor(reason, transient) {
    super(reason);
    this.name = 'ProviderError';
    this.reason = reason;
    this.transient = transient;
  }
}

/**
 * Modes:
 *   'ok'           always succeeds
 *   'timeout_once' fails with a timeout on the 1st call, then succeeds  (transient failure that recovers)
 *   'always_down'  always fails with a timeout/503                      (transient failure that never recovers)
 *   'reject'       always rejects, e.g. invalid recipient               (permanent failure)
 */
function createFakeProvider(mode = 'ok') {
  let calls = 0;

  return {
    get calls() {
      return calls;
    },
    async send() {
      calls += 1;
      switch (mode) {
        case 'ok':
          return { messageId: `fake-${calls}` };
        case 'timeout_once':
          if (calls === 1) throw new ProviderError('PROVIDER_TIMEOUT', true);
          return { messageId: `fake-${calls}` };
        case 'always_down':
          throw new ProviderError('PROVIDER_UNAVAILABLE', true);
        case 'reject':
          throw new ProviderError('PROVIDER_REJECTED', false);
        default:
          throw new Error(`Unknown fake provider mode: ${mode}`);
      }
    },
  };
}

module.exports = { ProviderError, createFakeProvider };
