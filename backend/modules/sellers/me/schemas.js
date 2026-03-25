import {
  sanitizeString,
  sanitizeBoolean,
  sanitizePayoutMode,
  validateEfiPayeeCode,
  sanitizeDecimalPositive,
  sanitizeInteger,
  sanitizePhone
} from '../../../sanitize.js';

function normalizeUf(value) {
  return sanitizeString(value || '').toUpperCase().slice(0, 2);
}

function normalizePostalCode(value) {
  return sanitizeString(value || '').replace(/\D+/g, '').slice(0, 8);
}

export function hasAnyValue(obj) {
  return Object.values(obj).some(v => v !== undefined && v !== null && `${v}`.trim() !== '');
}

export function sanitizeSellerPayload(payload = {}) {
  const base = {
    nome_loja: sanitizeString(payload.nome_loja),
    categoria: sanitizeString(payload.categoria),
    descricao_loja: sanitizeString(payload.descricao_loja),
    logo_url: sanitizeString(payload.logo_url),
    is_efi_connected: sanitizeBoolean(payload.is_efi_connected),
    commission_rate: sanitizeDecimalPositive(payload.commission_rate, { allowZero: true }),
    manual_payout_fee_rate: sanitizeDecimalPositive(payload.manual_payout_fee_rate, { allowZero: true }),
    payout_delay_days: sanitizeInteger(payload.payout_delay_days)
  };

  const payoutModeValidation = sanitizePayoutMode(payload.payout_mode);
  if (!payoutModeValidation.ok) {
    return { ok: false, code: 'VALIDATION_ERROR', message: payoutModeValidation.reason };
  }

  const efiPayeeValidation = validateEfiPayeeCode(payload.efi_payee_code, base.is_efi_connected);
  if (!efiPayeeValidation.ok) {
    return { ok: false, code: 'VALIDATION_ERROR', message: efiPayeeValidation.reason };
  }

  base.payout_mode = payoutModeValidation.value;
  base.efi_payee_code = efiPayeeValidation.value;

  const billingData = {
    legal_name: sanitizeString(payload.legal_name),
    cpf_cnpj: sanitizeString(payload.cpf_cnpj),
    bank_name: sanitizeString(payload.bank_name),
    bank_agency: sanitizeString(payload.bank_agency),
    bank_account: sanitizeString(payload.bank_account),
    pix_key: sanitizeString(payload.pix_key),
    pix_key_type: sanitizeString(payload.pix_key_type)
  };

  const shippingData = {
    from_postal_code: normalizePostalCode(payload.from_postal_code),
    from_address_line: sanitizeString(payload.from_address_line),
    from_number: sanitizeString(payload.from_number),
    from_district: sanitizeString(payload.from_district),
    from_city: sanitizeString(payload.from_city),
    from_state: normalizeUf(payload.from_state),
    from_country: sanitizeString(payload.from_country || 'BR').toUpperCase().slice(0, 2),
    contact_name: sanitizeString(payload.contact_name),
    contact_phone: sanitizePhone(payload.contact_phone),
    document_type: sanitizeString(payload.document_type),
    document_number: sanitizeString(payload.document_number)
  };

  return { ok: true, value: { ...base, billingData, shippingData } };
}
