<?php
class RefundPaymentHandler
{
    public function handle(array $ctx): void
    {
        if ($ctx['method'] !== 'POST') {
            Response::methodNotAllowed();
        }

        $user   = Auth::requireAuth($ctx['headers']);
        $body   = $ctx['body'];

        $paymentId       = Sanitize::integer($body['payment_id'] ?? $body['paymentId'] ?? null);
        $orderId         = Sanitize::integer($body['order_id'] ?? $body['orderId'] ?? null);
        $reason          = Sanitize::string($body['reason'] ?? '');
        $requestedAmount = isset($body['amount']) ? Sanitize::number($body['amount']) : null;

        if (!$paymentId && !$orderId) {
            Response::error('VALIDATION_ERROR', 'payment_id ou order_id obrigatório');
        }

        if ($requestedAmount !== null && $requestedAmount <= 0) {
            Response::error('VALIDATION_ERROR', 'amount deve ser maior que zero');
        }

        $result = Database::transaction(function (PDO $pdo) use ($user, $paymentId, $orderId, $requestedAmount, $reason) {
            // Busca pagamento reembolsável
            if ($paymentId) {
                $selector = 'p.id = ?';
                $selectorId = $paymentId;
            } else {
                $selector = 'p.order_id = ?';
                $selectorId = $orderId;
            }

            $stmt = $pdo->prepare(
                "SELECT p.id, p.order_id, p.provider, p.provider_charge_id, p.amount, p.status,
                        o.comprador_id, o.vendedor_id
                 FROM payments p
                 JOIN orders o ON o.id = p.order_id
                 WHERE {$selector}
                   AND (o.comprador_id = ? OR o.vendedor_id IN (SELECT id FROM sellers WHERE user_id = ?))
                 ORDER BY p.created_at DESC LIMIT 1 FOR UPDATE"
            );
            $stmt->execute([$selectorId, $user['id'], $user['id']]);
            $payment = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$payment) {
                throw new RuntimeException('Pagamento não encontrado ou sem permissão');
            }

            $paymentAmount = (float)$payment['amount'];
            $currentStatus = strtolower($payment['status']);

            if (!in_array($currentStatus, ['approved', 'partially_refunded'], true)) {
                throw new BusinessException('INVALID_PAYMENT_STATUS', 'Pagamento sem saldo para refund');
            }

            // Calcula saldo reembolsável
            $refundedStmt = $pdo->prepare(
                'SELECT COALESCE(SUM(amount), 0) as refunded_total FROM refunds WHERE payment_id = ? AND status = \'processed\''
            );
            $refundedStmt->execute([$payment['id']]);
            $refundedTotal = (float)($refundedStmt->fetch(PDO::FETCH_ASSOC)['refunded_total'] ?? 0);
            $refundable    = round($paymentAmount - $refundedTotal, 2);

            if ($refundable <= 0) {
                throw new BusinessException('NO_REFUNDABLE_BALANCE', 'Não existe saldo reembolsável');
            }

            $amountToRefund = round($requestedAmount ?? $refundable, 2);
            if ($amountToRefund <= 0 || $amountToRefund - $refundable > 0.009) {
                throw new BusinessException('REFUND_AMOUNT_EXCEEDS_BALANCE', 'Valor solicitado excede o saldo reembolsável');
            }

            if ($payment['provider'] !== 'efi') {
                throw new RuntimeException('No MVP, refund disponível apenas para provider EFI');
            }

            // Chama EFI para criar refund
            $providerRefund = EfiClient::createPixRefund([
                'providerChargeId' => $payment['provider_charge_id'],
                'amount'           => $amountToRefund,
                'refundReference'  => 'refund_' . time(),
                'reason'           => $reason,
            ]);

            $refundStatus   = in_array(strtolower($providerRefund['status'] ?? ''), ['processed', 'pending'], true)
                ? strtolower($providerRefund['status'])
                : 'processed';
            $refundableAfter = round($refundable - $amountToRefund, 2);

            // Salva refund (tabela refunds — criada no schema_mysql.sql)
            $refundStmt = $pdo->prepare(
                'INSERT INTO refunds (payment_id, order_id, provider, provider_refund_id, amount,
                 reason, status, raw_response_json, requested_by_user_id, processed_at, updated_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?,
                         CASE WHEN ? = \'processed\' THEN CURRENT_TIMESTAMP ELSE NULL END,
                         CURRENT_TIMESTAMP)'
            );
            $refundStmt->execute([
                $payment['id'], $payment['order_id'], $payment['provider'],
                $providerRefund['provider_refund_id'] ?? null,
                $amountToRefund, $reason, $refundStatus,
                json_encode($providerRefund),
                $user['id'], $refundStatus,
            ]);
            $refundId = (int)$pdo->lastInsertId();

            // Atualiza status do pagamento
            if ($refundStatus === 'processed') {
                $nextPaymentStatus = $refundableAfter <= 0 ? 'refunded' : 'partially_refunded';
                $pdo->prepare('UPDATE payments SET status = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?')
                    ->execute([$nextPaymentStatus, $payment['id']]);
                $pdo->prepare(
                    'UPDATE orders SET payment_status = ?,
                     status = CASE WHEN ? = \'refunded\' AND status IN (\'pendente\',\'confirmado\',\'enviado\') THEN \'cancelado\' ELSE status END
                     WHERE id = ?'
                )->execute([$nextPaymentStatus, $nextPaymentStatus, $payment['order_id']]);
            }

            $refundRow = $pdo->prepare('SELECT * FROM refunds WHERE id = ?');
            $refundRow->execute([$refundId]);

            return [
                'refund'            => $refundRow->fetch(PDO::FETCH_ASSOC),
                'payment'           => ['id' => $payment['id'], 'order_id' => $payment['order_id'], 'status' => $refundStatus === 'processed' ? ($refundableAfter <= 0 ? 'refunded' : 'partially_refunded') : $currentStatus],
                'refundable_before' => $refundable,
                'refundable_after'  => $refundableAfter,
                'provider'          => $providerRefund,
            ];
        });

        Response::success($result);
    }
}
