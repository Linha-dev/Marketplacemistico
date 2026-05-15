<?php
class CreatePaymentHandler
{
    public function handle(array $ctx): void
    {
        if ($ctx['method'] !== 'POST') {
            Response::methodNotAllowed();
        }

        $user = Auth::requireAuth($ctx['headers']);
        RateLimit::check(5 * 60 * 1000, 10, 'Muitas tentativas de pagamento. Aguarde alguns minutos.');

        $body          = $ctx['body'];
        $orderId       = Sanitize::integer($body['order_id'] ?? $body['orderId'] ?? null);
        $paymentMethod = Sanitize::string($body['payment_method'] ?? $body['paymentMethod'] ?? 'pix');

        if (!$orderId) {
            Response::error('VALIDATION_ERROR', 'order_id obrigatório');
        }

        // Busca pedido + dados do vendedor
        $orders = Database::query(
            'SELECT o.id, o.comprador_id, o.vendedor_id, o.total, o.grand_total,
                    s.id as seller_id, s.nome_loja, s.is_efi_connected, s.efi_payee_code,
                    s.commission_rate, s.manual_payout_fee_rate
             FROM orders o
             JOIN sellers s ON s.id = o.vendedor_id
             WHERE o.id = ? AND o.comprador_id = ?',
            [$orderId, $user['id']]
        );

        if (empty($orders)) {
            Response::notFound('Pedido não encontrado');
        }

        $buyers = Database::query('SELECT id, nome, email, cpf_cnpj FROM users WHERE id = ?', [$user['id']]);
        if (empty($buyers)) {
            Response::notFound('Comprador não encontrado');
        }

        $order  = $orders[0];
        $buyer  = $buyers[0];
        $amount = (float)($order['grand_total'] ?: $order['total']);

        // Cria cobrança EFI
        $splitConfig = ($order['is_efi_connected'] && $order['efi_payee_code'])
            ? ['mode' => 'efi_split', 'recipient_code' => $order['efi_payee_code']]
            : ['mode' => 'manual'];

        $chargePayload = $this->buildChargePayload($order, $buyer, $amount, $splitConfig, $paymentMethod);
        $charge        = EfiClient::createPixCharge($chargePayload);

        $providerChargeId = $charge['provider_charge_id'] ?? ($charge['txid'] ?? null);
        $pixQrCode        = $charge['pix_qr_code']    ?? ($charge['imagemQrcode']   ?? null);
        $pixCopyPaste     = $charge['pix_copy_paste'] ?? ($charge['qrcode']         ?? null);
        $chargeStatus     = strtolower($charge['status'] ?? 'pending');
        $splitMode        = $splitConfig['mode'];

        // Salva pagamento
        Database::execute(
            'INSERT INTO payments (order_id, provider, provider_charge_id, payment_method, status, amount, raw_response_json)
             VALUES (?, \'efi\', ?, ?, ?, ?, ?)',
            [$orderId, $providerChargeId, $paymentMethod, $chargeStatus, $amount, json_encode($charge)]
        );
        $paymentId = (int)Database::getConnection()->lastInsertId();
        $payments  = Database::query('SELECT * FROM payments WHERE id = ?', [$paymentId]);
        $payment   = $payments[0];

        // Calcula splits
        $commissionRate      = (float)($order['commission_rate'] ?? 0.12);
        $manualFeeRate       = (float)($order['manual_payout_fee_rate'] ?? 0);
        $platformFee         = $amount * $commissionRate;
        $operationalFee      = ($splitMode === 'manual') ? $amount * $manualFeeRate : 0;
        $sellerNet           = max(0, $amount - $platformFee - $operationalFee);
        $splitStatus         = $chargeStatus === 'approved' ? 'ready' : 'pending';

        Database::execute(
            'INSERT INTO payment_splits (payment_id, seller_id, split_mode, gross_amount, platform_fee_amount,
             gateway_fee_amount, operational_fee_amount, seller_net_amount, efi_payee_code_snapshot, status)
             VALUES (?, ?, ?, ?, ?, 0, ?, ?, ?, ?)',
            [
                $paymentId, $order['seller_id'], $splitMode, $amount, $platformFee,
                $operationalFee, $sellerNet, $splitConfig['recipient_code'] ?? null, $splitStatus,
            ]
        );

        // Manual payout entry
        if ($splitMode === 'manual') {
            Database::execute(
                'INSERT INTO manual_payouts (seller_id, order_id, amount, fee_amount, status, scheduled_for)
                 VALUES (?, ?, ?, ?, \'pending\', NOW())',
                [$order['seller_id'], $orderId, $sellerNet, $operationalFee]
            );
        }

        Response::success([
            'payment'     => $payment,
            'pixQrCode'   => $pixQrCode,
            'pixCopyPaste' => $pixCopyPaste,
            'splitMode'   => $splitMode,
        ]);
    }

    private function buildChargePayload(array $order, array $buyer, float $amount, array $splitConfig, string $paymentMethod): array
    {
        if ($paymentMethod !== 'pix') {
            throw new RuntimeException('No MVP, somente PIX está habilitado');
        }

        $cpfCnpj = Sanitize::cpfCnpj($buyer['cpf_cnpj'] ?? '');

        $payload = [
            'calendario' => ['expiracao' => 3600],
            'devedor'    => [
                'cpf'  => strlen($cpfCnpj) === 11 ? $cpfCnpj : null,
                'cnpj' => strlen($cpfCnpj) === 14 ? $cpfCnpj : null,
                'nome' => $buyer['nome'] ?? 'Comprador',
            ],
            'valor'      => ['original' => number_format($amount, 2, '.', '')],
            'chave'      => Config::require('EFI_PIX_KEY'),
            'infoAdicionais' => [
                ['nome' => 'order_id', 'valor' => (string)$order['id']],
            ],
        ];

        if ($splitConfig['mode'] === 'efi_split' && !empty($splitConfig['recipient_code'])) {
            $payload['split'] = [
                'divisaoTarifa' => 'assumidoTotal',
                'minhaParte'    => ['tipo' => 'percentual', 'valor' => '12'],
                'repasse'       => [
                    ['tipo' => 'percentual', 'valor' => '88', 'favorecido' => ['chave' => $splitConfig['recipient_code']]],
                ],
            ];
        }

        return $payload;
    }
}
