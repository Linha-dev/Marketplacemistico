import { sanitizeInteger } from '../../../sanitize.js';
import { findPublishedProductsBySellerId, findSellerPublicProfileById } from './repository.js';

function createBusinessError(code, message) {
  const error = new Error(message);
  error.code = code;
  return error;
}

export async function getSellerPublicProfile(rawId) {
  const sellerId = sanitizeInteger(rawId);

  if (!sellerId) {
    throw createBusinessError('INVALID_ID', 'ID inválido');
  }

  const sellers = await findSellerPublicProfileById(sellerId);
  if (sellers.length === 0) {
    throw createBusinessError('NOT_FOUND', 'Vendedor não encontrado');
  }

  const products = await findPublishedProductsBySellerId(sellerId);

  return {
    seller: sellers[0],
    products
  };
}
