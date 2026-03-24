import * as db from '../../../db.js';

const { query } = db;

export async function findOrderForPayment(orderId, buyerId) {
  return query(
    `SELECT o.id, o.comprador_id, o.vendedor_id, o.total, o.grand_total,
            s.id as seller_id, s.nome_loja, s.is_efi_connected, s.efi_payee_code,
            s.commission_rate, s.manual_payout_fee_rate
     FROM orders o
     JOIN sellers s ON s.id = o.vendedor_id
     WHERE o.id = $1 AND o.comprador_id = $2`,
    [orderId, buyerId]
  );
}

export async function findBuyerById(userId) {
  return query('SELECT id, nome, email, cpf_cnpj FROM users WHERE id = $1', [userId]);
}

export async function insertPayment(payload) {
  return query(
    `INSERT INTO payments (
       order_id, provider, provider_charge_id, payment_method, status, amount, raw_response_json, paid_at
     )
     VALUES ($1, 'efi', $2, $3, $4, $5, $6::jsonb, $7)
     RETURNING *`,
    [
      payload.orderId,
      payload.providerChargeId,
      payload.paymentMethod,
      payload.status,
      payload.amount,
      JSON.stringify(payload.rawResponse || {}),
      payload.paidAt
    ]
  );
}

export async function insertPaymentSplit(payload) {
  return query(
    `INSERT INTO payment_splits (
       payment_id, seller_id, split_mode, gross_amount, platform_fee_amount,
       gateway_fee_amount, operational_fee_amount, seller_net_amount,
       efi_payee_code_snapshot, status
     )
     VALUES ($1, $2, $3, $4, $5, 0, $6, $7, $8, $9)`,
    [
      payload.paymentId,
      payload.sellerId,
      payload.splitMode,
      payload.grossAmount,
      payload.platformFeeAmount,
      payload.operationalFeeAmount,
      payload.sellerNetAmount,
      payload.efiPayeeCodeSnapshot,
      payload.status
    ]
  );
}

export async function insertManualPayout(payload) {
  return query(
    `INSERT INTO manual_payouts (
       seller_id, order_id, amount, fee_amount, status, scheduled_for
     )
     VALUES ($1, $2, $3, $4, 'pending', NOW())`,
    [payload.sellerId, payload.orderId, payload.amount, payload.feeAmount]
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

export async function findRefundablePayment(tx, selectorId, userId, byPaymentId) {
  const selector = byPaymentId ? 'p.id = $1' : 'p.order_id = $1';

  return tx.query(
    `SELECT p.id, p.order_id, p.provider, p.provider_charge_id, p.amount, p.status,
            o.comprador_id, o.vendedor_id
     FROM payments p
     JOIN orders o ON o.id = p.order_id
     WHERE ${selector}
       AND (o.comprador_id = $2 OR o.vendedor_id IN (SELECT id FROM sellers WHERE user_id = $2))
     ORDER BY p.created_at DESC
     LIMIT 1
     FOR UPDATE OF p`,
    [selectorId, userId]
  );
}
