import { query } from '../../../db.js';

export async function findSellerByUserId(userId) {
  return query('SELECT id FROM sellers WHERE user_id = $1', [userId]);
}

export async function countPublishedProducts(baseQuery, params) {
  const result = await query(`SELECT COUNT(*) as total ${baseQuery}`, params);
  return parseInt(result[0].total, 10);
}

export async function findPublishedProducts(baseQuery, params, limit, offset, paramCount) {
  return query(
    `SELECT p.id, p.nome, p.categoria, p.descricao, p.preco, p.estoque, p.imagem_url,
            p.publicado, p.created_at, p.weight_kg, p.height_cm, p.width_cm, p.length_cm, p.insurance_value,
            s.id as seller_id, s.nome_loja, s.avaliacao_media
     ${baseQuery}
     ORDER BY p.created_at DESC
     LIMIT $${paramCount} OFFSET $${paramCount + 1}`,
    [...params, limit, offset]
  );
}

export async function createProduct(payload) {
  return query(
    `INSERT INTO products (
       seller_id, nome, categoria, descricao, preco, estoque, imagem_url, publicado,
       weight_kg, height_cm, width_cm, length_cm, insurance_value
     )
     VALUES ($1, $2, $3, $4, $5, $6, $7, $8, $9, $10, $11, $12, $13)
     RETURNING *`,
    [
      payload.sellerId,
      payload.nome,
      payload.categoria,
      payload.descricao,
      payload.preco,
      payload.estoque,
      payload.imagemUrl || '',
      payload.publicado,
      payload.weightKg,
      payload.heightCm,
      payload.widthCm,
      payload.lengthCm,
      payload.insuranceValue
    ]
  );
}

export async function findProductById(productId) {
  return query(
    `SELECT p.*, s.nome_loja, s.user_id as vendedor_id, s.avaliacao_media
     FROM products p
     JOIN sellers s ON p.seller_id = s.id
     WHERE p.id = $1`,
    [productId]
  );
}

export async function updateProductBySeller(payload) {
  return query(
    `UPDATE products
     SET nome=$1, categoria=$2, descricao=$3, preco=$4, estoque=$5, imagem_url=$6, publicado=$7,
         weight_kg=$8, height_cm=$9, width_cm=$10, length_cm=$11, insurance_value=$12,
         updated_at=CURRENT_TIMESTAMP
     WHERE id=$13 AND seller_id=$14
     RETURNING *`,
    [
      payload.nome,
      payload.categoria,
      payload.descricao,
      payload.preco,
      payload.estoque,
      payload.imagemUrl || '',
      payload.publicado,
      payload.weightKg,
      payload.heightCm,
      payload.widthCm,
      payload.lengthCm,
      payload.insuranceValue,
      payload.productId,
      payload.sellerId
    ]
  );
}

export async function deleteProductBySeller(productId, sellerId) {
  return query('DELETE FROM products WHERE id = $1 AND seller_id = $2 RETURNING id', [productId, sellerId]);
}

export async function findProductPublishState(productId, sellerId) {
  return query(
    `SELECT publicado, weight_kg, height_cm, width_cm, length_cm
     FROM products
     WHERE id = $1 AND seller_id = $2`,
    [productId, sellerId]
  );
}

export async function toggleProductPublish(novoEstado, productId, sellerId) {
  return query(
    'UPDATE products SET publicado = $1, updated_at = CURRENT_TIMESTAMP WHERE id = $2 AND seller_id = $3 RETURNING *',
    [novoEstado, productId, sellerId]
  );
}

export async function countProductsBySeller(sellerId) {
  const result = await query('SELECT COUNT(*) as total FROM products WHERE seller_id = $1', [sellerId]);
  return parseInt(result[0].total, 10);
}

export async function findProductsBySeller(sellerId, limit, offset) {
  return query(
    `SELECT * FROM products WHERE seller_id = $1
     ORDER BY created_at DESC LIMIT $2 OFFSET $3`,
    [sellerId, limit, offset]
  );
}
