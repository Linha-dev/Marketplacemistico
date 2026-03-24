import { sendError, sendSuccess } from '../../../response.js';
import { getAlertsReport, getMetricsReport } from './service.js';

function statusForCode(code) {
  if (code === 'UNAUTHORIZED') return 401;
  if (code === 'METHOD_NOT_ALLOWED') return 405;
  return 500;
}

function handleError(res, error, fallbackMessage) {
  const code = error.code || 'INTERNAL_ERROR';
  return sendError(res, code, error.message || fallbackMessage, statusForCode(code));
}

export async function observabilityAlertsController(req, res) {
  try {
    if (req.method !== 'GET') {
      return sendError(res, 'METHOD_NOT_ALLOWED', 'Metodo nao permitido', 405);
    }

    const payload = await getAlertsReport(req);
    return sendSuccess(res, payload);
  } catch (error) {
    return handleError(res, error, 'Erro ao gerar alertas operacionais');
  }
}

export async function observabilityMetricsController(req, res) {
  try {
    if (req.method !== 'GET') {
      return sendError(res, 'METHOD_NOT_ALLOWED', 'Metodo nao permitido', 405);
    }

    const payload = await getMetricsReport(req);
    return sendSuccess(res, payload);
  } catch (error) {
    return handleError(res, error, 'Erro ao consultar metricas operacionais');
  }
}
