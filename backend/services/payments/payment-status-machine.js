import { sanitizeString } from '../../sanitize.js';

export const PAYMENT_STATUSES = Object.freeze([
  'pending',
  'approved',
  'failed',
  'cancelled',
  'partially_refunded',
  'refunded'
]);

const statusAliases = new Map([
  ['pending', 'pending'],
  ['waiting', 'pending'],
  ['created', 'pending'],
  ['approved', 'approved'],
  ['paid', 'approved'],
  ['concluded', 'approved'],
  ['concluida', 'approved'],
  ['failed', 'failed'],
  ['rejected', 'failed'],
  ['cancelled', 'cancelled'],
  ['canceled', 'cancelled'],
  ['partially_refunded', 'partially_refunded'],
  ['partial_refund', 'partially_refunded'],
  ['partially refunded', 'partially_refunded'],
  ['refunded', 'refunded']
]);

const validTransitions = new Map([
  ['pending', new Set(['pending', 'approved', 'failed', 'cancelled'])],
  ['approved', new Set(['approved', 'partially_refunded', 'refunded'])],
  ['failed', new Set(['failed'])],
  ['cancelled', new Set(['cancelled'])],
  ['partially_refunded', new Set(['partially_refunded', 'refunded'])],
  ['refunded', new Set(['refunded'])]
]);

export function normalizePaymentStatus(rawStatus) {
  const normalizedInput = sanitizeString(rawStatus || '').toLowerCase();
  return statusAliases.get(normalizedInput) || 'pending';
}

export function canTransitionPaymentStatus(currentStatus, nextStatus) {
  const current = normalizePaymentStatus(currentStatus);
  const next = normalizePaymentStatus(nextStatus);
  const allowed = validTransitions.get(current);
  return Boolean(allowed && allowed.has(next));
}

export function assertPaymentStatusTransition(currentStatus, nextStatus) {
  if (!canTransitionPaymentStatus(currentStatus, nextStatus)) {
    const error = new Error(`Transicao de status invalida: ${currentStatus} -> ${nextStatus}`);
    error.code = 'INVALID_PAYMENT_STATUS_TRANSITION';
    throw error;
  }
}
