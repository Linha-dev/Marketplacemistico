import {
  sanitizeString,
  sanitizeNumber,
  sanitizeInteger,
  sanitizeBoolean,
  validateDimensions,
  validateImageUrl
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
  const search = sanitizeString(query.search);
  return {
    categoria: sanitizeString(query.categoria),
    sellerId: sanitizeInteger(query.seller_id),
    search: search ? search.slice(0, 200) : search
  };
}

export function sanitizeProductPayload(payload = {}) {
  const imageValidation = validateImageUrl(payload.imagemUrl);

  const sanitized = {
    nome: sanitizeString(payload.nome),
    categoria: sanitizeString(payload.categoria),
    descricao: sanitizeString(payload.descricao),
    preco: sanitizeNumber(payload.preco),
    estoque: sanitizeInteger(payload.estoque),
    imagemUrl: imageValidation.ok ? imageValidation.value : null,
    publicado: sanitizeBoolean(payload.publicado)
  };

  const dimensionsValidation = validateDimensions({
    weightKg: payload.weightKg ?? payload.weight_kg,
    heightCm: payload.heightCm ?? payload.height_cm,
    widthCm: payload.widthCm ?? payload.width_cm,
    lengthCm: payload.lengthCm ?? payload.length_cm,
    insuranceValue: payload.insuranceValue ?? payload.insurance_value
  }, sanitized.publicado);

  return { sanitized, dimensionsValidation, imageValidation };
}

export function sanitizeProductId(id) {
  return sanitizeInteger(id);
}
