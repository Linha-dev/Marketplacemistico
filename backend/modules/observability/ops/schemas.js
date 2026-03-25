import { sanitizeString } from '../../../sanitize.js';

export function readAlertsSecret(headers = {}, query = {}, body = {}) {
  return sanitizeString(
    headers['x-alerts-secret'] ||
    query?.alerts_secret ||
    body?.alerts_secret
  );
}

export function readMetricsSecret(headers = {}, query = {}, body = {}) {
  return sanitizeString(
    headers['x-metrics-secret'] ||
    query?.metrics_secret ||
    body?.metrics_secret
  );
}
