import { query } from '../../../db.js';

export async function findSellerPublicProfileById(sellerId) {
  return query(
    `SELECT s.id, s.nome_loja, s.categoria, s.descricao_loja, s.logo_url,
            s.avaliacao_media, s.total_vendas, s.created_at,
            u.nome as vendedor_nome
     FROM sellers s
     JOIN users u ON s.user_id = u.id
     WHERE s.id = $1`,
    [sellerId]
  );
}

export async function findPublishedProductsBySellerId(sellerId) {
  return query(
    `SELECT id, nome, categoria, descricao, preco, estoque, imagem_url
     FROM products
     WHERE seller_id = $1 AND publicado = true
     ORDER BY created_at DESC`,
    [sellerId]
  );
}
