import { query } from '../../../db.js';

const BASE_PROFILE_QUERY = `SELECT u.id, u.tipo, u.nome, u.email, u.telefone, u.cpf_cnpj, u.tipo_documento,
                                   u.created_at, u.updated_at,
                                   s.id as seller_id, s.nome_loja, s.categoria, s.descricao_loja
                            FROM users u
                            LEFT JOIN sellers s ON u.id = s.user_id
                            WHERE u.id = $1`;

export async function findUserProfileById(userId) {
  return query(BASE_PROFILE_QUERY, [userId]);
}

export async function findUserByPhoneExcludingId(phone, userId) {
  return query(
    'SELECT id FROM users WHERE telefone = $1 AND id <> $2 LIMIT 1',
    [phone, userId]
  );
}

export async function updateUserProfile({ userId, nome, telefone }) {
  return query(
    'UPDATE users SET nome = $1, telefone = $2, updated_at = CURRENT_TIMESTAMP WHERE id = $3',
    [nome, telefone || null, userId]
  );
}
