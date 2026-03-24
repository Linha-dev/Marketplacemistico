import { sendSuccess, sendError } from '../../../response.js';
import { getSellerProfile, updateSellerProfile } from './service.js';

function statusForCode(code) {
  if (code === 'NOT_FOUND') return 404;
  if (code === 'METHOD_NOT_ALLOWED') return 405;
  if (code === 'VALIDATION_ERROR' || code === 'STORE_NAME_TAKEN') return 400;
  return 500;
}

export default async function sellersMeController(req, res) {
  try {
    if (req.method === 'GET') {
      const seller = await getSellerProfile(req.user.id);
      return sendSuccess(res, { seller });
    }

    if (req.method === 'PUT') {
      const seller = await updateSellerProfile(req.user.id, req.body);
      return sendSuccess(res, { seller });
    }

    return sendError(res, 'METHOD_NOT_ALLOWED', 'Metodo nao permitido', 405);
  } catch (error) {
    const code = error.code || 'INTERNAL_ERROR';
    const message = error.message || 'Erro ao processar dados do vendedor';
    return sendError(res, code, message, statusForCode(code));
  }
}
