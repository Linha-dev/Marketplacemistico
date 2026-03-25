import * as db from '../../../db.js';

const { query } = db;

export async function canViewOrderLedger(orderId, userId) {
  return query(
    `SELECT o.id
     FROM orders o
     JOIN sellers s ON s.id = o.vendedor_id
     WHERE o.id = $1
       AND (o.comprador_id = $2 OR s.user_id = $2)
     LIMIT 1`,
    [orderId, userId]
  );
}

export async function countManualPayouts(status) {
  const values = [];
  let whereClause = '';

  if (status) {
    values.push(status);
    whereClause = 'WHERE status = $1';
  }

  const countRows = await query(
    `SELECT COUNT(*) AS total
     FROM manual_payouts
     ${whereClause}`,
    values
  );

  return Number(countRows[0]?.total || 0);
}

export async function listManualPayouts(status, limit, offset) {
  const values = [];
  let whereClause = '';

  if (status) {
    values.push(status);
    whereClause = 'WHERE status = $1';
  }

  values.push(limit, offset);

  return query(
    `SELECT id, seller_id, order_id, amount, fee_amount, status,
            scheduled_for, paid_at, external_reference,
            proof_url, review_reason, approved_at, rejected_at, created_at
     FROM manual_payouts
     ${whereClause}
     ORDER BY created_at DESC
     LIMIT $${values.length - 1}
     OFFSET $${values.length}`,
    values
  );
}

export async function runInTransaction(fn) {
  if (typeof db.withTransaction === 'function') {
    return db.withTransaction(fn);
  }

  const fakeTx = {
    query: (text, params) => query(text, params)
  };

  return fn(fakeTx);
}

export async function findManualPayoutForUpdate(tx, payoutId) {
  return tx.query(
    `SELECT id, seller_id, order_id, amount, fee_amount, status,
            external_reference, proof_url
     FROM manual_payouts
     WHERE id = $1
     FOR UPDATE`,
    [payoutId]
  );
}

export async function updateManualPayout(tx, payload) {
  return tx.query(
    `UPDATE manual_payouts
     SET status = $2,
         review_reason = COALESCE($3, review_reason),
         proof_url = COALESCE($4, proof_url),
         external_reference = COALESCE($5, external_reference),
         approved_at = CASE WHEN $2 = 'approved' THEN CURRENT_TIMESTAMP ELSE approved_at END,
         rejected_at = CASE WHEN $2 = 'rejected' THEN CURRENT_TIMESTAMP ELSE rejected_at END,
         paid_at = CASE WHEN $2 = 'paid' THEN CURRENT_TIMESTAMP ELSE paid_at END
     WHERE id = $1
     RETURNING *`,
    [
      payload.payoutId,
      payload.toStatus,
      payload.reason || null,
      payload.proofUrl || null,
      payload.externalReference || null
    ]
  );
}

export async function insertManualPayoutAction(tx, payload) {
  return tx.query(
    `INSERT INTO manual_payout_actions (
       manual_payout_id, action, previous_status, new_status,
       reason, proof_url, external_reference, acted_by_user_id
     )
     VALUES ($1, $2, $3, $4, $5, $6, $7, $8)
     RETURNING *`,
    [
      payload.payoutId,
      payload.action,
      payload.previousStatus,
      payload.newStatus,
      payload.reason || null,
      payload.proofUrl || null,
      payload.externalReference || null,
      payload.actorUserId
    ]
  );
}

export async function findLatestPaymentByOrderId(tx, orderId) {
  return tx.query(
    `SELECT id
     FROM payments
     WHERE order_id = $1
     ORDER BY created_at DESC
     LIMIT 1`,
    [orderId]
  );
}
