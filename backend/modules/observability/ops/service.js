import { generateOperationalAlerts } from '../../../observability/alerts-service.js';
import { getMetricsSnapshot } from '../../../observability/metrics-store.js';
import { readAlertsSecret, readMetricsSecret } from './schemas.js';

function createBusinessError(code, message) {
  const error = new Error(message);
  error.code = code;
  return error;
}

export async function getAlertsReport(req) {
  const configured = process.env.ALERTS_SECRET;
  if (configured) {
    const provided = readAlertsSecret(req.headers, req.query, req.body);
    if (provided !== configured) {
      throw createBusinessError('UNAUTHORIZED', 'Acesso de alertas sem autorizacao');
    }
  }

  const report = await generateOperationalAlerts();

  return {
    generated_at: report.generated_at,
    total_alerts: report.total_alerts,
    config: report.config,
    alerts: report.alerts
  };
}

export async function getMetricsReport(req) {
  const configured = process.env.METRICS_SECRET;
  if (configured) {
    const provided = readMetricsSecret(req.headers, req.query, req.body);
    if (provided !== configured) {
      throw createBusinessError('UNAUTHORIZED', 'Acesso de metricas sem autorizacao');
    }
  }

  return {
    metrics: getMetricsSnapshot(),
    collected_at: new Date().toISOString()
  };
}
