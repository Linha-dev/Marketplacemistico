import { jest } from '@jest/globals';
import { generateOperationalAlerts } from '../../backend/observability/alerts-service.js';

describe('alerts service', () => {
  afterEach(() => {
    jest.useRealTimers();
  });

  test('generates alerts for webhook spike, queue stuck and reconciliation anomalies', async () => {
    jest.useFakeTimers().setSystemTime(new Date('2026-03-16T12:00:00.000Z'));

    const db = { query: jest.fn() };
    db.query
      .mockResolvedValueOnce({ rows: [{ total_failed: 8 }] })
      .mockResolvedValueOnce({ rows: [{ total_stuck: 2 }] })
      .mockResolvedValueOnce({
        rows: [{
          id: 77,
          run_date: '2026-03-15',
          status: 'failed',
          summary_json: { total_issues: 3, by_type: { WEBHOOK_MAX_RETRIES_REACHED: 2 } }
        }]
      });

    const report = await generateOperationalAlerts({ db });
    const alertCodes = report.alerts.map(alert => alert.code);

    expect(report.total_alerts).toBe(5);
    expect(alertCodes).toEqual(expect.arrayContaining([
      'WEBHOOK_ERROR_SPIKE',
      'WEBHOOK_QUEUE_STUCK',
      'RECONCILIATION_RUN_INCOMPLETE',
      'RECONCILIATION_STALE',
      'RECONCILIATION_ISSUES_DETECTED'
    ]));
  });

  test('supports custom thresholds through config overrides', async () => {
    jest.useFakeTimers().setSystemTime(new Date('2026-03-16T12:00:00.000Z'));

    const db = { query: jest.fn() };
    db.query
      .mockResolvedValueOnce({ rows: [{ total_failed: 4 }] })
      .mockResolvedValueOnce({ rows: [{ total_stuck: 0 }] })
      .mockResolvedValueOnce({
        rows: [{
          id: 10,
          run_date: '2026-03-16',
          status: 'completed',
          summary_json: { total_issues: 0 }
        }]
      });

    const report = await generateOperationalAlerts({
      db,
      config: {
        webhookFailedThreshold: 4,
        webhookFailedWindowMinutes: 10,
        webhookStuckMinutes: 5,
        reconciliationStaleDays: 0
      }
    });

    expect(report.total_alerts).toBe(1);
    expect(report.alerts[0].code).toBe('WEBHOOK_ERROR_SPIKE');
    expect(report.config.webhookFailedWindowMinutes).toBe(10);
  });
});
