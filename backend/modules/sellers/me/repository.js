import { query } from '../../../db.js';

export async function loadSellerByUserId(userId) {
  return query(
    `SELECT s.id, s.nome_loja, s.categoria, s.descricao_loja, s.logo_url,
            s.avaliacao_media, s.total_vendas, s.created_at,
            s.is_efi_connected, s.efi_payee_code, s.payout_mode,
            s.commission_rate, s.manual_payout_fee_rate, s.payout_delay_days,
            u.nome, u.email, u.telefone,
            bp.legal_name, bp.cpf_cnpj as billing_cpf_cnpj, bp.bank_name, bp.bank_agency,
            bp.bank_account, bp.pix_key, bp.pix_key_type,
            sp.from_postal_code, sp.from_address_line, sp.from_number, sp.from_district,
            sp.from_city, sp.from_state, sp.from_country, sp.contact_name,
            sp.contact_phone, sp.document_type, sp.document_number
     FROM sellers s
     JOIN users u ON s.user_id = u.id
     LEFT JOIN seller_billing_profiles bp ON bp.seller_id = s.id
     LEFT JOIN seller_shipping_profiles sp ON sp.seller_id = s.id
     WHERE s.user_id = $1`,
    [userId]
  );
}

export async function findStoreNameConflict(nomeLoja, userId) {
  return query(
    'SELECT id FROM sellers WHERE LOWER(nome_loja) = LOWER($1) AND user_id <> $2 LIMIT 1',
    [nomeLoja, userId]
  );
}

export async function updateSellerBase(userId, data) {
  return query(
    `UPDATE sellers
     SET nome_loja = $1,
         categoria = $2,
         descricao_loja = $3,
         logo_url = $4,
         is_efi_connected = $5,
         efi_payee_code = $6,
         payout_mode = $7,
         commission_rate = COALESCE($8, commission_rate),
         manual_payout_fee_rate = COALESCE($9, manual_payout_fee_rate),
         payout_delay_days = COALESCE($10, payout_delay_days)
     WHERE user_id = $11`,
    [
      data.nome_loja,
      data.categoria,
      data.descricao_loja || '',
      data.logo_url || '',
      data.is_efi_connected,
      data.efi_payee_code || null,
      data.payout_mode,
      data.commission_rate,
      data.manual_payout_fee_rate,
      data.payout_delay_days,
      userId
    ]
  );
}

export async function getSellerIdByUserId(userId) {
  const rows = await query('SELECT id FROM sellers WHERE user_id = $1', [userId]);
  return rows[0]?.id;
}

export async function upsertBillingProfile(sellerId, data) {
  return query(
    `INSERT INTO seller_billing_profiles (
       seller_id, legal_name, cpf_cnpj, bank_name, bank_agency, bank_account, pix_key, pix_key_type
     )
     VALUES ($1, $2, $3, $4, $5, $6, $7, $8)
     ON CONFLICT (seller_id)
     DO UPDATE SET
       legal_name = EXCLUDED.legal_name,
       cpf_cnpj = EXCLUDED.cpf_cnpj,
       bank_name = EXCLUDED.bank_name,
       bank_agency = EXCLUDED.bank_agency,
       bank_account = EXCLUDED.bank_account,
       pix_key = EXCLUDED.pix_key,
       pix_key_type = EXCLUDED.pix_key_type,
       updated_at = CURRENT_TIMESTAMP`,
    [
      sellerId,
      data.legal_name || null,
      data.cpf_cnpj || null,
      data.bank_name || null,
      data.bank_agency || null,
      data.bank_account || null,
      data.pix_key || null,
      data.pix_key_type || null
    ]
  );
}

export async function upsertShippingProfile(sellerId, data) {
  return query(
    `INSERT INTO seller_shipping_profiles (
       seller_id, from_postal_code, from_address_line, from_number, from_district,
       from_city, from_state, from_country, contact_name, contact_phone,
       document_type, document_number
     )
     VALUES ($1, $2, $3, $4, $5, $6, $7, $8, $9, $10, $11, $12)
     ON CONFLICT (seller_id)
     DO UPDATE SET
       from_postal_code = EXCLUDED.from_postal_code,
       from_address_line = EXCLUDED.from_address_line,
       from_number = EXCLUDED.from_number,
       from_district = EXCLUDED.from_district,
       from_city = EXCLUDED.from_city,
       from_state = EXCLUDED.from_state,
       from_country = EXCLUDED.from_country,
       contact_name = EXCLUDED.contact_name,
       contact_phone = EXCLUDED.contact_phone,
       document_type = EXCLUDED.document_type,
       document_number = EXCLUDED.document_number,
       updated_at = CURRENT_TIMESTAMP`,
    [
      sellerId,
      data.from_postal_code,
      data.from_address_line || null,
      data.from_number || null,
      data.from_district || null,
      data.from_city || null,
      data.from_state,
      data.from_country,
      data.contact_name || null,
      data.contact_phone || null,
      data.document_type || null,
      data.document_number || null
    ]
  );
}
