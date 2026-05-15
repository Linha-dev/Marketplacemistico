<?php
class EfiWebhookHandler
{
    public function handle(array $ctx): void
    {
        if ($ctx['method'] !== 'POST') {
            Response::methodNotAllowed();
        }

        $webhookSecret  = Config::require('EFI_WEBHOOK_SECRET');
        $receivedSecret = $ctx['headers']['x-webhook-secret'] ?? $ctx['headers']['X-Webhook-Secret'] ?? '';

        if ($receivedSecret !== $webhookSecret) {
            Response::error('UNAUTHORIZED', 'Webhook sem autorização', 401);
        }

        $body = $ctx['body'];

        // Salva evento no banco
        $externalId = $body['txid'] ?? $body['id'] ?? null;
        $eventType  = $body['evento'] ?? $body['type'] ?? 'pix.payment';

        // Idempotência: ignora duplicatas
        if ($externalId) {
            $existing = Database::query(
                'SELECT id FROM webhook_events WHERE provider = ? AND external_id = ? AND event_type = ? LIMIT 1',
                ['efi', $externalId, $eventType]
            );
            if (!empty($existing)) {
                Response::success(['message' => 'Evento já processado', 'duplicate' => true]);
            }
        }

        Database::execute(
            'INSERT INTO webhook_events (provider, event_type, external_id, payload_json, status)
             VALUES (?, ?, ?, ?, ?)',
            ['efi', $eventType, $externalId, json_encode($body), 'received']
        );
        $webhookEventId = (int)Database::getConnection()->lastInsertId();

        // Atualiza status do pagamento baseado no evento
        $processed = false;
        if ($externalId && in_array($eventType, ['PAGAMENTO_RECEBIDO', 'pix.payment', 'pix_payment'], true)) {
            $payments = Database::query(
                'SELECT p.id, p.order_id, p.amount FROM payments p WHERE p.provider_charge_id = ? LIMIT 1',
                [$externalId]
            );
            if (!empty($payments)) {
                $payment = $payments[0];
                Database::execute(
                    'UPDATE payments SET status = ?, paid_at = CURRENT_TIMESTAMP, updated_at = CURRENT_TIMESTAMP WHERE id = ?',
                    ['approved', $payment['id']]
                );
                Database::execute(
                    'UPDATE orders SET payment_status = ? WHERE id = ?',
                    ['approved', $payment['order_id']]
                );
                // Marca split como ready
                Database::execute(
                    'UPDATE payment_splits SET status = ? WHERE payment_id = ?',
                    ['ready', $payment['id']]
                );
                $processed = true;
            }
        }

        // Marca evento como processado
        Database::execute(
            'UPDATE webhook_events SET status = ?, processed_at = CURRENT_TIMESTAMP WHERE id = ?',
            [$processed ? 'processed' : 'ignored', $webhookEventId]
        );

        Response::success(['message' => 'Webhook EFI recebido', 'processed' => $processed]);
    }
}
