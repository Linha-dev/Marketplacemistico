import { sanitizeString } from '../sanitize.js';
import { sendError, sendSuccess } from '../response.js';
import { withCors } from '../middleware.js';
import { requireInternalRole } from '../auth-middleware.js';
import { generateOperationalAlerts } from './alerts-service.js';

function validateAlertsSecret(req, res) {
  const configured = process.env.ALERTS_SECRET;
  if (!configured) {
    return true;
  }

  const provided = sanitizeString(
    req.headers['x-alerts-secret'] ||
    req.query?.alerts_secret ||
    req.body?.alerts_secret
  );

  if (provided !== configured) {
    sendError(res, 'UNAUTHORIZED', 'Acesso de alertas sem autorizacao', 401);
    return false;
  }

  return true;
}

async function handler(req, res) {
  if (req.method !== 'GET') {
    return sendError(res, 'METHOD_NOT_ALLOWED', 'Metodo nao permitido', 405);
  }

  if (!validateAlertsSecret(req, res)) {
    return undefined;
  }

  const report = await generateOperationalAlerts();

  return sendSuccess(res, {
    generated_at: report.generated_at,
    total_alerts: report.total_alerts,
    config: report.config,
    alerts: report.alerts
  });
}

export default withCors(requireInternalRole(handler));
