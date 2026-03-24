import { sanitizeInteger, sanitizeString } from '../../../sanitize.js';

export const ACTION_TRANSITIONS = {
  approve: {
    from: new Set(['pending']),
    to: 'approved'
  },
  reject: {
    from: new Set(['pending', 'approved']),
    to: 'rejected'
  },
  pay: {
    from: new Set(['approved']),
    to: 'paid'
  }
};

export function sanitizeOrderId(orderIdRaw) {
  return sanitizeInteger(orderIdRaw);
}

export function sanitizeRunDate(value) {
  const requestedDate = sanitizeString(value || '');
  return /^\d{4}-\d{2}-\d{2}$/.test(requestedDate)
    ? requestedDate
    : new Date().toISOString().slice(0, 10);
}

export function sanitizeManualPayoutListQuery(query = {}) {
  const status = sanitizeString(query.status || '').toLowerCase();
  const page = Math.max(1, sanitizeInteger(query.page) || 1);
  const limit = Math.min(100, Math.max(1, sanitizeInteger(query.limit) || 20));

  return {
    status,
    page,
    limit,
    offset: (page - 1) * limit
  };
}

export function sanitizeManualPayoutActionPayload(query = {}, body = {}) {
  return {
    payoutId: sanitizeInteger(query.id),
    action: sanitizeString(body.action || '').toLowerCase(),
    reason: sanitizeString(body.reason || ''),
    proofUrl: sanitizeString(body.proof_url || ''),
    externalReference: sanitizeString(body.external_reference || '')
  };
}
