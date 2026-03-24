import { sanitizeString, sanitizePhone, validatePhone } from '../../../sanitize.js';

export function sanitizeProfilePayload(payload = {}) {
  return {
    nome: sanitizeString(payload.nome),
    telefone: sanitizePhone(payload.telefone)
  };
}

export function validateProfilePayload(payload) {
  if (!payload.nome) {
    return { ok: false, code: 'VALIDATION_ERROR', message: 'Nome e obrigatorio' };
  }

  if (payload.telefone) {
    const phoneValidation = validatePhone(payload.telefone);
    if (!phoneValidation.ok) {
      return { ok: false, code: 'VALIDATION_ERROR', message: phoneValidation.reason };
    }

    return {
      ok: true,
      value: {
        ...payload,
        telefone: phoneValidation.value
      }
    };
  }

  return { ok: true, value: payload };
}
