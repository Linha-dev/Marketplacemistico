import { sendError } from './response.js';

const store = new Map();

/**
 * Simple in-memory rate limiter HOC.
 *
 * NOTE: In-memory state is per-process. With multiple Fly.io instances,
 * each process tracks its own counters.
 * For production with real traffic, replace with a distributed store (e.g. Redis).
 *
 * @param {object} opts
 * @param {number} opts.windowMs   - Time window in milliseconds
 * @param {number} opts.max        - Max requests per window per IP
 * @param {string} [opts.message]  - Error message returned when limit is exceeded
 */
export function createRateLimit({ windowMs, max, message = 'Muitas tentativas. Aguarde alguns minutos.' }) {
  return function withRateLimit(handler) {
    return async function rateLimitedHandler(req, res) {
      const forwarded = req.headers['x-forwarded-for'];
      const ip = (forwarded ? forwarded.split(',')[0] : null) ||
        req.socket?.remoteAddress ||
        'unknown';

      const path = (req.url || '').split('?')[0];
      const key = `${ip}:${path}`;
      const now = Date.now();

      let record = store.get(key);
      if (!record || now - record.windowStart >= windowMs) {
        record = { count: 1, windowStart: now };
      } else {
        record.count += 1;
      }
      store.set(key, record);

      if (record.count > max) {
        const retryAfter = Math.ceil((record.windowStart + windowMs - now) / 1000);
        res.setHeader('Retry-After', String(retryAfter));
        return sendError(res, 'RATE_LIMIT_EXCEEDED', message, 429);
      }

      return handler(req, res);
    };
  };
}
