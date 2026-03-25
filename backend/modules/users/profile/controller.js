import { sendSuccess, sendError } from '../../../response.js';
import { getUserProfile, updateProfile } from './service.js';

function statusForCode(code) {
  if (code === 'NOT_FOUND') return 404;
  if (code === 'METHOD_NOT_ALLOWED') return 405;
  if (code === 'VALIDATION_ERROR' || code === 'PHONE_TAKEN') return 400;
  return 500;
}

export default async function usersProfileController(req, res) {
  try {
    if (req.method === 'GET') {
      const user = await getUserProfile(req.user.id);
      return sendSuccess(res, { user });
    }

    if (req.method === 'PUT') {
      const user = await updateProfile(req.user.id, req.body);
      return sendSuccess(res, { user });
    }

    return sendError(res, 'METHOD_NOT_ALLOWED', 'Metodo nao permitido', 405);
  } catch (error) {
    const code = error.code || 'INTERNAL_ERROR';
    const message = error.message || 'Erro ao processar perfil';
    return sendError(res, code, message, statusForCode(code));
  }
}
