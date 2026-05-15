<?php
class SellersMeHandler
{
    public function handle(array $ctx): void
    {
        $user = Auth::requireVendedor($ctx['headers']);

        if ($ctx['method'] === 'GET') {
            $this->get($user);
        } elseif (in_array($ctx['method'], ['PUT', 'PATCH'], true)) {
            $this->update($ctx, $user);
        } else {
            Response::methodNotAllowed();
        }
    }

    private function get(array $user): void
    {
        $sellers = Database::query(
            'SELECT s.id, s.nome_loja, s.categoria, s.descricao_loja, s.logo_url,
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
             WHERE s.user_id = ?',
            [$user['id']]
        );

        if (empty($sellers)) {
            Response::notFound('Perfil de vendedor não encontrado');
        }
        Response::success(['seller' => $sellers[0]]);
    }

    private function update(array $ctx, array $user): void
    {
        $body        = $ctx['body'];
        $nomeLoja    = Sanitize::string($body['nome_loja'] ?? '');
        $categoria   = Sanitize::string($body['categoria'] ?? '');
        $descricao   = Sanitize::string($body['descricao_loja'] ?? '');
        $logoUrl     = Sanitize::url($body['logo_url'] ?? '');
        $isEfi       = Sanitize::boolean($body['is_efi_connected'] ?? false);
        $efiCode     = Sanitize::string($body['efi_payee_code'] ?? '');
        $payoutMode  = Sanitize::sanitizePayoutMode($body['payout_mode'] ?? '');
        $commRate    = Sanitize::decimalPositive($body['commission_rate'] ?? null, true);
        $manFeeRate  = Sanitize::decimalPositive($body['manual_payout_fee_rate'] ?? null, true);
        $delayDays   = Sanitize::integer($body['payout_delay_days'] ?? null);

        if (!$payoutMode['ok']) {
            Response::error('VALIDATION_ERROR', $payoutMode['reason']);
        }

        if (!$nomeLoja || !$categoria) {
            Response::error('VALIDATION_ERROR', 'Nome da loja e categoria são obrigatórios');
        }

        // Verifica conflito de nome (excluindo o próprio)
        $conflict = Database::query(
            'SELECT id FROM sellers WHERE LOWER(nome_loja) = LOWER(?) AND user_id != ? LIMIT 1',
            [$nomeLoja, $user['id']]
        );
        if (!empty($conflict)) {
            Response::error('STORE_NAME_TAKEN', 'Nome da loja já cadastrado');
        }

        $sellers = Database::query('SELECT id FROM sellers WHERE user_id = ?', [$user['id']]);
        if (empty($sellers)) {
            Response::notFound('Vendedor não encontrado');
        }
        $sellerId = $sellers[0]['id'];

        Database::execute(
            'UPDATE sellers SET nome_loja=?, categoria=?, descricao_loja=?, logo_url=?, is_efi_connected=?,
             efi_payee_code=?, payout_mode=?,
             commission_rate=COALESCE(?, commission_rate),
             manual_payout_fee_rate=COALESCE(?, manual_payout_fee_rate),
             payout_delay_days=COALESCE(?, payout_delay_days)
             WHERE user_id=?',
            [
                $nomeLoja, $categoria, $descricao, $logoUrl, (int)$isEfi,
                $efiCode ?: null, $payoutMode['value'],
                $commRate, $manFeeRate, $delayDays, $user['id'],
            ]
        );

        // Billing profile (upsert)
        $billing = $body['billing'] ?? [];
        if (!empty($billing)) {
            Database::execute(
                'INSERT INTO seller_billing_profiles (seller_id, legal_name, cpf_cnpj, bank_name, bank_agency, bank_account, pix_key, pix_key_type)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE
                   legal_name=VALUES(legal_name), cpf_cnpj=VALUES(cpf_cnpj), bank_name=VALUES(bank_name),
                   bank_agency=VALUES(bank_agency), bank_account=VALUES(bank_account),
                   pix_key=VALUES(pix_key), pix_key_type=VALUES(pix_key_type), updated_at=CURRENT_TIMESTAMP',
                [
                    $sellerId,
                    Sanitize::string($billing['legal_name'] ?? ''),
                    Sanitize::cpfCnpj($billing['cpf_cnpj'] ?? ''),
                    Sanitize::string($billing['bank_name'] ?? ''),
                    Sanitize::string($billing['bank_agency'] ?? ''),
                    Sanitize::string($billing['bank_account'] ?? ''),
                    Sanitize::string($billing['pix_key'] ?? ''),
                    Sanitize::string($billing['pix_key_type'] ?? ''),
                ]
            );
        }

        // Shipping profile (upsert)
        $shipping = $body['shipping'] ?? [];
        if (!empty($shipping)) {
            $fromCep = preg_replace('/\D/', '', $shipping['from_postal_code'] ?? '');
            if (strlen($fromCep) !== 8) {
                Response::error('VALIDATION_ERROR', 'CEP de origem inválido');
            }
            $fromState = strtoupper(substr(Sanitize::string($shipping['from_state'] ?? ''), 0, 2));
            if (strlen($fromState) !== 2) {
                Response::error('VALIDATION_ERROR', 'UF de origem inválida');
            }

            Database::execute(
                'INSERT INTO seller_shipping_profiles (seller_id, from_postal_code, from_address_line, from_number, from_district,
                 from_city, from_state, from_country, contact_name, contact_phone, document_type, document_number)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE
                   from_postal_code=VALUES(from_postal_code), from_address_line=VALUES(from_address_line),
                   from_number=VALUES(from_number), from_district=VALUES(from_district),
                   from_city=VALUES(from_city), from_state=VALUES(from_state), from_country=VALUES(from_country),
                   contact_name=VALUES(contact_name), contact_phone=VALUES(contact_phone),
                   document_type=VALUES(document_type), document_number=VALUES(document_number),
                   updated_at=CURRENT_TIMESTAMP',
                [
                    $sellerId, $fromCep,
                    Sanitize::string($shipping['from_address_line'] ?? ''),
                    Sanitize::string($shipping['from_number'] ?? ''),
                    Sanitize::string($shipping['from_district'] ?? ''),
                    Sanitize::string($shipping['from_city'] ?? ''),
                    $fromState,
                    strtoupper(substr(Sanitize::string($shipping['from_country'] ?? 'BR'), 0, 2)) ?: 'BR',
                    Sanitize::string($shipping['contact_name'] ?? ''),
                    Sanitize::string($shipping['contact_phone'] ?? ''),
                    Sanitize::string($shipping['document_type'] ?? ''),
                    Sanitize::string($shipping['document_number'] ?? ''),
                ]
            );
        }

        $this->get($user);
    }
}
