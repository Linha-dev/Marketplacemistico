import { jest } from '@jest/globals';

jest.unstable_mockModule('../../backend/observability/alerts-service.js', () => ({
  generateOperationalAlerts: jest.fn()
}));

jest.unstable_mockModule('../../backend/auth-middleware.js', () => ({
  requireInternalRole: (handler) => async (req, res) => {
    if (!req.user) req.user = { id: 1, role: 'operator' };
    return handler(req, res);
  }
}));

const { generateOperationalAlerts } = await import('../../backend/observability/alerts-service.js');
const { default: handler } = await import('../../backend/observability/alerts.js');

describe('Observability alerts API', () => {
  let req;
  let res;

  beforeEach(() => {
    jest.clearAllMocks();
    delete process.env.ALERTS_SECRET;

    req = {
      method: 'GET',
      headers: {},
      query: {},
      body: {},
      user: { id: 1, role: 'operator' }
    };

    res = {
      status: jest.fn().mockReturnThis(),
      json: jest.fn().mockReturnThis(),
      setHeader: jest.fn().mockReturnThis()
    };
  });

  afterEach(() => {
    delete process.env.ALERTS_SECRET;
  });

  test('returns operational alerts report', async () => {
    generateOperationalAlerts.mockResolvedValueOnce({
      generated_at: '2026-03-16T10:00:00.000Z',
      total_alerts: 1,
      config: { webhookFailedThreshold: 5 },
      alerts: [{ code: 'WEBHOOK_ERROR_SPIKE', severity: 'high' }]
    });

    await handler(req, res);

    expect(generateOperationalAlerts).toHaveBeenCalledTimes(1);
    expect(res.status).toHaveBeenCalledWith(200);
    expect(res.json).toHaveBeenCalledWith(expect.objectContaining({
      success: true,
      data: expect.objectContaining({
        total_alerts: 1,
        alerts: expect.arrayContaining([
          expect.objectContaining({ code: 'WEBHOOK_ERROR_SPIKE' })
        ])
      })
    }));
  });

  test('rejects request when alerts secret is required and missing', async () => {
    process.env.ALERTS_SECRET = 'secret_alerts';

    await handler(req, res);

    expect(generateOperationalAlerts).not.toHaveBeenCalled();
    expect(res.status).toHaveBeenCalledWith(401);
    expect(res.json).toHaveBeenCalledWith(expect.objectContaining({
      success: false,
      error: expect.objectContaining({
        code: 'UNAUTHORIZED'
      })
    }));
  });
});
