import * as db from '../../../db.js';

const { query } = db;

export async function countOrdersByBuyerId(userId) {
  const result = await query('SELECT COUNT(*) as total FROM orders WHERE comprador_id = $1', [userId]);
  return parseInt(result[0].total, 10);
}

export async function findOrdersByBuyerId(userId, limit, offset) {
  return query(
    `SELECT o.id, o.total, o.grand_total, o.items_subtotal, o.shipping_total,
            o.payment_status, o.shipping_status, o.status, o.created_at,
            s.nome_loja as vendedor_nome
     FROM orders o
     JOIN sellers s ON o.vendedor_id = s.id
     WHERE o.comprador_id = $1
     ORDER BY o.created_at DESC LIMIT $2 OFFSET $3`,
    [userId, limit, offset]
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

export async function findLockedProductsByIds(tx, productIds) {
  return tx.query(
    `SELECT p.id, p.nome, p.preco, p.estoque, p.seller_id, p.publicado,
            p.weight_kg, p.height_cm, p.width_cm, p.length_cm,
            s.user_id as seller_user_id
     FROM products p
     JOIN sellers s ON p.seller_id = s.id
     WHERE p.id = ANY($1::int[])
     FOR UPDATE OF p`,
    [productIds]
  );
}

export async function insertOrder(tx, payload) {
  return tx.query(
    `INSERT INTO orders (
       comprador_id, vendedor_id, total, items_subtotal, shipping_total, discount_total,
       grand_total, status, payment_status, shipping_status, selected_shipping_quote_id,
       shipping_address_snapshot_json, billing_address_snapshot_json
     )
     VALUES ($1, $2, $3, $4, $5, $6, $7, 'pendente', 'pending', 'pending', $8, $9, $10)
     RETURNING id`,
    [
      payload.buyerId,
      payload.sellerId,
      payload.grandTotal,
      payload.itemsSubtotal,
      payload.shippingTotal,
      payload.discountTotal,
      payload.grandTotal,
      payload.shippingQuoteId,
      payload.shippingAddressSnapshot ? JSON.stringify(payload.shippingAddressSnapshot) : null,
      payload.billingAddressSnapshot ? JSON.stringify(payload.billingAddressSnapshot) : null
    ]
  );
}

export async function insertOrderItem(tx, payload) {
  return tx.query(
    `INSERT INTO order_items (
       order_id, seller_id, product_id, quantidade, preco_unitario,
       unit_price, name_snapshot, weight_snapshot, dimension_snapshot_json
     )
     VALUES ($1, $2, $3, $4, $5, $6, $7, $8, $9)`,
    [
      payload.orderId,
      payload.sellerId,
      payload.productId,
      payload.quantidade,
      payload.preco,
      payload.preco,
      payload.nameSnapshot,
      payload.weightSnapshot,
      JSON.stringify(payload.dimensionSnapshot)
    ]
  );
}

export async function decreaseProductStock(tx, requestedQty, productId) {
  return tx.query('UPDATE products SET estoque = estoque - $1 WHERE id = $2 AND estoque >= $1', [requestedQty, productId]);
}

export async function findOrderWithItemsById(tx, orderId) {
  return tx.query(
    `SELECT o.*, array_agg(json_build_object(
       'product_id', oi.product_id,
       'quantidade', oi.quantidade,
       'preco_unitario', oi.preco_unitario,
       'name_snapshot', oi.name_snapshot,
       'weight_snapshot', oi.weight_snapshot,
       'dimension_snapshot_json', oi.dimension_snapshot_json
     )) as items
     FROM orders o
     JOIN order_items oi ON o.id = oi.order_id
     WHERE o.id = $1
     GROUP BY o.id`,
    [orderId]
  );
}

export async function findOrderByIdForUser(orderId, userId) {
  return query(
    `SELECT o.id, o.total, o.status, o.created_at,
            s.nome_loja as vendedor_nome,
            u.nome as comprador_nome
     FROM orders o
     JOIN sellers s ON o.vendedor_id = s.id
     JOIN users u ON o.comprador_id = u.id
     WHERE o.id = $1
       AND (o.comprador_id = $2 OR s.user_id = $2)`,
    [orderId, userId]
  );
}

export async function findOrderItems(orderId) {
  return query(
    `SELECT oi.*, p.nome as produto_nome, p.imagem_url
     FROM order_items oi
     JOIN products p ON oi.product_id = p.id
     WHERE oi.order_id = $1`,
    [orderId]
  );
}

export async function findSellerByUserId(userId) {
  return query('SELECT id FROM sellers WHERE user_id = $1', [userId]);
}

export async function findSellerOrderStatus(orderId, sellerId) {
  return query(
    `SELECT id, status, shipping_status, payment_status
     FROM orders
     WHERE id = $1 AND vendedor_id = $2
     LIMIT 1`,
    [orderId, sellerId]
  );
}

export async function updateOrderStatus(orderId, sellerId, status) {
  return query(
    `UPDATE orders SET status = $1
     WHERE id = $2 AND vendedor_id = $3
     RETURNING *`,
    [status, orderId, sellerId]
  );
}

export async function findOrderForPostSale(tx, orderId) {
  return tx.query(
    `SELECT id, comprador_id, vendedor_id, status, shipping_status, payment_status
     FROM orders
     WHERE id = $1
     FOR UPDATE`,
    [orderId]
  );
}

export async function updateOrderPostSale(tx, orderId, status, shippingStatus) {
  return tx.query(
    `UPDATE orders
     SET status = $2,
         shipping_status = $3
     WHERE id = $1`,
    [orderId, status, shippingStatus]
  );
}

export async function findLatestPaymentForOrder(tx, orderId) {
  return tx.query(
    `SELECT id, order_id, provider, provider_charge_id, amount, status
     FROM payments
     WHERE order_id = $1
     ORDER BY created_at DESC
     LIMIT 1
     FOR UPDATE OF payments`,
    [orderId]
  );
}

export async function insertOrderPostSaleEvent(tx, payload) {
  return tx.query(
    `INSERT INTO order_post_sale_events (
       order_id, action, previous_status, new_status,
       previous_shipping_status, new_shipping_status,
       reason, requested_by_user_id, refund_id, metadata_json
     )
     VALUES ($1, $2, $3, $4, $5, $6, $7, $8, $9, $10::jsonb)`,
    [
      payload.orderId,
      payload.action,
      payload.previousStatus,
      payload.nextStatus,
      payload.previousShippingStatus,
      payload.nextShippingStatus,
      payload.reason || null,
      payload.userId,
      payload.refundId || null,
      JSON.stringify(payload.metadata || {})
    ]
  );
}

export async function findOrderBasicById(tx, orderId) {
  return tx.query(
    `SELECT id, status, shipping_status, payment_status
     FROM orders
     WHERE id = $1`,
    [orderId]
  );
}
