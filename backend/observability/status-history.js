import { query } from '../db.js';
import { sendError, sendSuccess } from '../response.js';

export default async function handler(req, res) {
  if (req.method !== 'GET') {
    return sendError(res, 'METHOD_NOT_ALLOWED', 'Método não permitido', 405);
  }

  try {
    const rows = await query(
      `SELECT t, s FROM status_checks ORDER BY t DESC LIMIT 48`
    );

    const checks = rows.map(row => ({
      t: row.t,
      api: row.s & 0x03,
      db:  (row.s >> 2) & 0x03,
    }));

    return sendSuccess(res, { checks });
  } catch (err) {
    return sendError(res, 'INTERNAL_ERROR', 'Erro ao buscar histórico', 500);
  }
}
