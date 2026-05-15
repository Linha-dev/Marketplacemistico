<?php
class MelhorEnvioWebhookHandler
{
    public function handle(array $ctx): void
    {
        if ($ctx['method'] !== 'POST') {
            Response::methodNotAllowed();
        }

        $webhookSecret  = Config::require('MELHOR_ENVIO_WEBHOOK_SECRET');
        $receivedSecret = $ctx['headers']['x-webhook-secret'] ?? $ctx['headers']['X-Webhook-Secret'] ?? '';

        if ($receivedSecret !== $webhookSecret) {
            Response::error('UNAUTHORIZED', 'Webhook sem autorização', 401);
        }

        $body       = $ctx['body'];
        $eventType  = Sanitize::string($body['type'] ?? $body['event'] ?? 'shipping.update');
        $externalId = Sanitize::string($body['id'] ?? $body['order_id'] ?? '');

        // Idempotência
        if ($externalId) {
            $existing = Database::query(
                'SELECT id FROM webhook_events WHERE provider = ? AND external_id = ? AND event_type = ? LIMIT 1',
                ['melhor_envio', $externalId, $eventType]
            );
            if (!empty($existing)) {
                Response::success(['message' => 'Evento já processado', 'duplicate' => true]);
            }
        }

        Database::execute(
            'INSERT INTO webhook_events (provider, event_type, external_id, payload_json, status)
             VALUES (?, ?, ?, ?, ?)',
            ['melhor_envio', $eventType, $externalId ?: null, json_encode($body), 'received']
        );
        $webhookId = (int)Database::getConnection()->lastInsertId();

        $processed = false;
        // Atualiza envio se existir
        if ($externalId) {
            $shipments = Database::query(
                'SELECT id, order_id FROM shipments WHERE melhor_envio_shipment_id = ? LIMIT 1',
                [$externalId]
            );
            if (!empty($shipments)) {
                $status       = Sanitize::string($body['status'] ?? '');
                $trackingCode = Sanitize::string($body['tracking_code'] ?? $body['tracking'] ?? '');
                if ($status) {
                    Database::execute(
                        'UPDATE shipments SET status = ?, tracking_code = COALESCE(?, tracking_code), updated_at = CURRENT_TIMESTAMP WHERE id = ?',
                        [$status, $trackingCode ?: null, $shipments[0]['id']]
                    );
                }
                $processed = true;
            }
        }

        Database::execute(
            'UPDATE webhook_events SET status = ?, processed_at = CURRENT_TIMESTAMP WHERE id = ?',
            [$processed ? 'processed' : 'ignored', $webhookId]
        );

        Response::success(['message' => 'Webhook Melhor Envio recebido', 'processed' => $processed]);
    }
}
