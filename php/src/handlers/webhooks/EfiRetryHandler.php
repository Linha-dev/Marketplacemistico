<?php
class EfiRetryHandler
{
    public function handle(array $ctx): void
    {
        if ($ctx['method'] !== 'POST') {
            Response::methodNotAllowed();
        }

        $opsSecret      = Config::get('EFI_WEBHOOK_OPS_SECRET', '');
        $receivedSecret = $ctx['headers']['x-ops-secret'] ?? $ctx['headers']['X-Ops-Secret'] ?? '';
        if ($opsSecret === '' || $opsSecret !== $receivedSecret) {
            Response::error('UNAUTHORIZED', 'Ops secret inválido', 401);
        }

        // Busca eventos com falha pendente de retry
        $failedEvents = Database::query(
            'SELECT id, provider, event_type, external_id, payload_json
             FROM webhook_events WHERE status = ? ORDER BY created_at ASC LIMIT 50',
            ['failed']
        );

        $retried = 0;
        foreach ($failedEvents as $event) {
            $payload = json_decode((string)$event['payload_json'], true) ?? [];
            $externalId = $payload['txid'] ?? $payload['id'] ?? null;

            if ($externalId) {
                $payments = Database::query(
                    'SELECT id, order_id FROM payments WHERE provider_charge_id = ? LIMIT 1',
                    [$externalId]
                );
                if (!empty($payments)) {
                    Database::execute(
                        'UPDATE payments SET status = ?, paid_at = CURRENT_TIMESTAMP, updated_at = CURRENT_TIMESTAMP WHERE id = ?',
                        ['approved', $payments[0]['id']]
                    );
                    Database::execute(
                        'UPDATE orders SET payment_status = ? WHERE id = ?',
                        ['approved', $payments[0]['order_id']]
                    );
                }
            }

            Database::execute(
                'UPDATE webhook_events SET status = ?, processed_at = CURRENT_TIMESTAMP WHERE id = ?',
                ['processed', $event['id']]
            );
            $retried++;
        }

        Response::success(['retried' => $retried]);
    }
}
