import { normalizeShippingStatus } from '../../backend/webhooks/melhor-envio.js';

describe('normalizeShippingStatus', () => {
  test('returns known status values unchanged', () => {
    expect(normalizeShippingStatus('pending')).toBe('pending');
    expect(normalizeShippingStatus('in_transit')).toBe('in_transit');
    expect(normalizeShippingStatus('delivered')).toBe('delivered');
  });

  test('maps common Melhor Envio events to canonical statuses', () => {
    expect(normalizeShippingStatus('enviado')).toBe('posted');
    expect(normalizeShippingStatus('Em transito')).toBe('in_transit');
    expect(normalizeShippingStatus('entregue')).toBe('delivered');
    expect(normalizeShippingStatus('cancelado')).toBe('cancelled');
  });

  test('falls back to pending for unknown values', () => {
    expect(normalizeShippingStatus('qualquer_coisa')).toBe('pending');
    expect(normalizeShippingStatus('')).toBe('pending');
    expect(normalizeShippingStatus(null)).toBe('pending');
  });
});
