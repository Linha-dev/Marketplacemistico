import { query } from '../db.js';
import { logError, logInfo } from './logger.js';

const SLOW_THRESHOLD_MS = 3000;

function encodeStatus(api, db) {
  return (api & 0x03) | ((db & 0x03) << 2);
}

async function checkApi() {
  const start = Date.now();
  try {
    await query('SELECT 1');
    const ms = Date.now() - start;
    return ms < SLOW_THRESHOLD_MS ? 0 : 1;
  } catch {
    return 2;
  }
}

export async function runStatusCheck() {
  const dbStatus = await checkApi();
  // API is alive if we got here; degrade if db is slow
  const apiStatus = dbStatus === 2 ? 2 : 0;
  const s = encodeStatus(apiStatus, dbStatus);

  try {
    await query(
      `INSERT INTO status_checks (s) VALUES ($1)
       ON CONFLICT (t) DO NOTHING`,
      [s]
    );
    await query(`DELETE FROM status_checks WHERE t < NOW() - INTERVAL '24 hours'`);
    logInfo('status.check.saved', { s, apiStatus, dbStatus });
  } catch (err) {
    logError('status.check.save_error', err);
  }
}
