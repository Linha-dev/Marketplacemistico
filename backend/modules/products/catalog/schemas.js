import {
  sanitizeString,
  sanitizeNumber,
  sanitizeInteger,
  sanitizeUrl,
  sanitizeBoolean,
  validateDimensions
} from '../../../sanitize.js';

export function sanitizePagination(query = {}, defaultLimit = 20) {
  const page = Math.max(1, sanitizeInteger(query.page) || 1);
  const limit = Math.min(100, Math.max(1, sanitizeInteger(query.limit) || defaultLimit));
  return {
    page,
    limit,
    offset: (page - 1) * limit
  };
}

export function sanitizeCatalogFilters(query = {}) {
  return {
    categoria: sanitizeString(query.categoria),
    sellerId: sanitizeInteger(query.seller_id),
    search: sanitizeString(query.search)
  };
}

export function sanitizeProductPayload(payload = {}) {
  const sanitized = {
    nome: sanitizeString(payload.nome),
    categoria: sanitizeString(payload.categoria),
    descricao: sanitizeString(payload.descricao),
    preco: sanitizeNumber(payload.preco),
    estoque: sanitizeInteger(payload.estoque),
    imagemUrl: sanitizeUrl(payload.imagemUrl),
    publicado: sanitizeBoolean(payload.publicado)
  };

  const dimensionsValidation = validateDimensions({
    weightKg: payload.weightKg ?? payload.weight_kg,
    heightCm: payload.heightCm ?? payload.height_cm,
    widthCm: payload.widthCm ?? payload.width_cm,
    lengthCm: payload.lengthCm ?? payload.length_cm,
    insuranceValue: payload.insuranceValue ?? payload.insurance_value
  }, sanitized.publicado);

  return { sanitized, dimensionsValidation };
}

export function sanitizeProductId(id) {
  return sanitizeInteger(id);
}
