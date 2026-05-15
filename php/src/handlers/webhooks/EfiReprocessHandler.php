<?php
class EfiReprocessHandler
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

        $eventId = Sanitize::integer($ctx['body']['event_id'] ?? $ctx['query']['eventId'] ?? null);
        if (!$eventId) {
            Response::error('VALIDATION_ERROR', 'event_id obrigatório');
        }

        $events = Database::query('SELECT * FROM webhook_events WHERE id = ? LIMIT 1', [$eventId]);
        if (empty($events)) {
            Response::notFound('Evento não encontrado');
        }

        $event   = $events[0];
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
            ['processed', $eventId]
        );

        Response::success(['message' => 'Evento reprocessado', 'event_id' => $eventId]);
    }
}
