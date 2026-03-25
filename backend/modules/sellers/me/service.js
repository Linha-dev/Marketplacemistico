import {
  findStoreNameConflict,
  getSellerIdByUserId,
  loadSellerByUserId,
  updateSellerBase,
  upsertBillingProfile,
  upsertShippingProfile
} from './repository.js';
import { hasAnyValue, sanitizeSellerPayload } from './schemas.js';

function createBusinessError(code, message) {
  const error = new Error(message);
  error.code = code;
  return error;
}

export async function getSellerProfile(userId) {
  const sellers = await loadSellerByUserId(userId);

  if (sellers.length === 0) {
    throw createBusinessError('NOT_FOUND', 'Perfil de vendedor nao encontrado');
  }

  return sellers[0];
}

export async function updateSellerProfile(userId, payload) {
  const sanitized = sanitizeSellerPayload(payload);

  if (!sanitized.ok) {
    throw createBusinessError(sanitized.code, sanitized.message);
  }

  const data = sanitized.value;

  if (!data.nome_loja || !data.categoria) {
    throw createBusinessError('VALIDATION_ERROR', 'Nome da loja e categoria sao obrigatorios');
  }

  const existingStore = await findStoreNameConflict(data.nome_loja, userId);
  if (existingStore.length > 0) {
    throw createBusinessError('STORE_NAME_TAKEN', 'Nome da loja ja cadastrado');
  }

  await updateSellerBase(userId, data);

  const sellerId = await getSellerIdByUserId(userId);

  if (hasAnyValue(data.billingData)) {
    await upsertBillingProfile(sellerId, data.billingData);
  }

  if (hasAnyValue(data.shippingData)) {
    if (!data.shippingData.from_postal_code || data.shippingData.from_postal_code.length !== 8) {
      throw createBusinessError('VALIDATION_ERROR', 'CEP de origem invalido');
    }
    if (!data.shippingData.from_state || data.shippingData.from_state.length !== 2) {
      throw createBusinessError('VALIDATION_ERROR', 'UF de origem invalida');
    }

    await upsertShippingProfile(sellerId, data.shippingData);
  }

  return getSellerProfile(userId);
}
