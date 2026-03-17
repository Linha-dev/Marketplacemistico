import { query } from '../db.js';
import { sanitizeInteger } from '../sanitize.js';

function getRunner(db) {
  if (db && typeof db.query === 'function') {
    return (text, params) => db.query(text, params);
  }
  return (text, params) => query(text, params);
}

function toRows(result) {
  if (Array.isArray(result)) {
    return result;
  }
  if (result && Array.isArray(result.rows)) {
    return result.rows;
  }
  return [];
}

function toNumber(value) {
  const num = Number(value);
  return Number.isFinite(num) ? num : 0;
}

function toIsoDate(value) {
  if (!value) return '';
  const str = String(value);
  return str.slice(0, 10);
}

function parseSummary(summary) {
  if (!summary) return {};
  if (typeof summary === 'string') {
    try {
      return JSON.parse(summary);
    } catch {
      return {};
    }
  }
  return summary;
}

function toPositiveInteger(value, fallback, min = 1) {
  const parsed = sanitizeInteger(value);
  if (parsed === null || parsed < min) {
    return fallback;
  }
  return parsed;
}

function daysBetween(startDate, endDate) {
  const start = new Date(`${startDate}T00:00:00.000Z`);
  const end = new Date(`${endDate}T00:00:00.000Z`);
  if (Number.isNaN(start.getTime()) || Number.isNaN(end.getTime())) {
    return 0;
  }
  return Math.floor((end.getTime() - start.getTime()) / 86400000);
}

function resolveAlertConfig(overrides = {}) {
  return {
    webhookFailedThreshold: toPositiveInteger(
      overrides.webhookFailedThreshold ?? process.env.ALERT_WEBHOOK_FAILED_THRESHOLD,
      5
    ),
    webhookFailedWindowMinutes: toPositiveInteger(
      overrides.webhookFailedWindowMinutes ?? process.env.ALERT_WEBHOOK_FAILED_WINDOW_MINUTES,
      30
    ),
    webhookStuckMinutes: toPositiveInteger(
      overrides.webhookStuckMinutes ?? process.env.ALERT_WEBHOOK_STUCK_MINUTES,
      15
    ),
    reconciliationStaleDays: toPositiveInteger(
      overrides.reconciliationStaleDays ?? process.env.ALERT_RECONCILIATION_STALE_DAYS,
      0,
      0
    )
  };
}

export async function generateOperationalAlerts({ db, config } = {}) {
  const runner = getRunner(db);
  const alertConfig = resolveAlertConfig(config);
  const alerts = [];

  const webhookFailedRows = await runner(
    `SELECT COUNT(*) AS total_failed
     FROM webhook_events
     WHERE provider = 'efi'
       AND status = 'failed'
       AND created_at >= CURRENT_TIMESTAMP - ($1::int * INTERVAL '1 minute')`,
    [alertConfig.webhookFailedWindowMinutes]
  );

  const failedCount = toNumber(toRows(webhookFailedRows)[0]?.total_failed);
  if (failedCount >= alertConfig.webhookFailedThreshold) {
    alerts.push({
      code: 'WEBHOOK_ERROR_SPIKE',
      severity: 'high',
      message: 'Volume alto de falhas no webhook EFI acima do limite configurado',
      context: {
        failed_count: failedCount,
        threshold: alertConfig.webhookFailedThreshold,
        window_minutes: alertConfig.webhookFailedWindowMinutes
      }
    });
  }

  const stuckRows = await runner(
    `SELECT COUNT(*) AS total_stuck
     FROM webhook_events
     WHERE provider = 'efi'
       AND status = 'processing'
       AND locked_at IS NOT NULL
       AND locked_at < CURRENT_TIMESTAMP - ($1::int * INTERVAL '1 minute')`,
    [alertConfig.webhookStuckMinutes]
  );

  const stuckCount = toNumber(toRows(stuckRows)[0]?.total_stuck);
  if (stuckCount > 0) {
    alerts.push({
      code: 'WEBHOOK_QUEUE_STUCK',
      severity: 'high',
      message: 'Fila de webhook possui eventos presos em processamento',
      context: {
        stuck_events: stuckCount,
        stale_after_minutes: alertConfig.webhookStuckMinutes
      }
    });
  }

  const lastRunRows = await runner(
    `SELECT id, run_date, status, summary_json, started_at, finished_at
     FROM reconciliation_runs
     ORDER BY started_at DESC
     LIMIT 1`
  );

  const lastRun = toRows(lastRunRows)[0];
  if (!lastRun) {
    alerts.push({
      code: 'RECONCILIATION_NOT_RUN',
      severity: 'high',
      message: 'Nenhuma conciliacao diaria foi executada',
      context: {}
    });
  } else {
    const runDate = toIsoDate(lastRun.run_date);
    const today = new Date().toISOString().slice(0, 10);
    const summary = parseSummary(lastRun.summary_json);

    if (lastRun.status !== 'completed') {
      alerts.push({
        code: 'RECONCILIATION_RUN_INCOMPLETE',
        severity: 'high',
        message: 'Ultima conciliacao diaria nao foi concluida',
        context: {
          run_id: lastRun.id,
          run_date: runDate,
          status: lastRun.status
        }
      });
    }

    const delayDays = runDate ? daysBetween(runDate, today) : 0;
    if (runDate && delayDays > alertConfig.reconciliationStaleDays) {
      alerts.push({
        code: 'RECONCILIATION_STALE',
        severity: 'medium',
        message: 'Conciliacao diaria atrasada em relacao ao limite configurado',
        context: {
          run_id: lastRun.id,
          last_run_date: runDate,
          today,
          stale_days: delayDays,
          allowed_stale_days: alertConfig.reconciliationStaleDays
        }
      });
    }

    const totalIssues = toNumber(summary.total_issues);
    if (totalIssues > 0) {
      alerts.push({
        code: 'RECONCILIATION_ISSUES_DETECTED',
        severity: 'medium',
        message: 'Conciliacao identificou divergencias que exigem analise',
        context: {
          run_id: lastRun.id,
          total_issues: totalIssues,
          by_type: summary.by_type || {}
        }
      });
    }
  }

  return {
    generated_at: new Date().toISOString(),
    config: alertConfig,
    total_alerts: alerts.length,
    alerts
  };
}
