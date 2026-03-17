import {
  PAYMENT_STATUSES,
  assertPaymentStatusTransition,
  canTransitionPaymentStatus,
  normalizePaymentStatus
} from '../../backend/services/payments/payment-status-machine.js';

describe('payment status machine', () => {
  test('normalizes provider aliases to canonical statuses', () => {
    expect(normalizePaymentStatus('paid')).toBe('approved');
    expect(normalizePaymentStatus('concluida')).toBe('approved');
    expect(normalizePaymentStatus('canceled')).toBe('cancelled');
    expect(normalizePaymentStatus('rejected')).toBe('failed');
  });

  test('accepts valid transitions', () => {
    expect(canTransitionPaymentStatus('pending', 'approved')).toBe(true);
    expect(canTransitionPaymentStatus('approved', 'partially_refunded')).toBe(true);
    expect(canTransitionPaymentStatus('partially_refunded', 'refunded')).toBe(true);
    expect(canTransitionPaymentStatus('failed', 'failed')).toBe(true);
  });

  test('rejects invalid transitions', () => {
    expect(canTransitionPaymentStatus('approved', 'pending')).toBe(false);
    expect(canTransitionPaymentStatus('failed', 'approved')).toBe(false);
    expect(() => assertPaymentStatusTransition('approved', 'pending')).toThrow('Transicao de status invalida');
  });

  test('exposes a finite set of payment statuses', () => {
    expect(PAYMENT_STATUSES).toEqual([
      'pending',
      'approved',
      'failed',
      'cancelled',
      'partially_refunded',
      'refunded'
    ]);
  });
});
