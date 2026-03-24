import { sendError, sendSuccess } from '../../../response.js';
import { getSellerPublicProfile } from './service.js';

function statusForCode(code) {
  if (code === 'NOT_FOUND') return 404;
  if (code === 'METHOD_NOT_ALLOWED') return 405;
  if (code === 'INVALID_ID') return 400;
  return 500;
}

export default async function sellerPublicProfileController(req, res) {
  try {
    if (req.method !== 'GET') {
      return sendError(res, 'METHOD_NOT_ALLOWED', 'Método não permitido', 405);
    }

    const payload = await getSellerPublicProfile(req.query.id);
    return sendSuccess(res, payload);
  } catch (error) {
    const code = error.code || 'INTERNAL_ERROR';
    return sendError(res, code, error.message || 'Erro ao buscar vendedor', statusForCode(code));
  }
}
