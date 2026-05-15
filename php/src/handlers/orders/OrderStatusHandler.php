<?php
class OrderStatusHandler
{
    private const VALID_STATUSES = [
        'pendente', 'confirmado', 'em_preparacao', 'enviado', 'entregue', 'cancelado', 'devolvido'
    ];

    public function handle(array $ctx): void
    {
        if ($ctx['method'] !== 'PATCH') {
            Response::methodNotAllowed();
        }

        $user    = Auth::requireAuth($ctx['headers']);
        $orderId = Sanitize::integer($ctx['params']['id'] ?? null);
        if (!$orderId) {
            Response::error('INVALID_ID', 'ID inválido');
        }

        $status = Sanitize::string($ctx['body']['status'] ?? '');
        if (!in_array($status, self::VALID_STATUSES, true)) {
            Response::error(
                'VALIDATION_ERROR',
                'Status inválido. Valores aceitos: ' . implode(', ', self::VALID_STATUSES)
            );
        }

        // Somente vendedores podem alterar status
        $sellers = Database::query('SELECT id FROM sellers WHERE user_id = ?', [$user['id']]);
        if (empty($sellers)) {
            Response::forbidden('Acesso restrito a vendedores');
        }
        $sellerId = $sellers[0]['id'];

        $existing = Database::query(
            'SELECT id, status FROM orders WHERE id = ? AND vendedor_id = ?',
            [$orderId, $sellerId]
        );
        if (empty($existing)) {
            Response::notFound('Pedido não encontrado ou sem permissão');
        }

        Database::execute(
            'UPDATE orders SET status = ? WHERE id = ? AND vendedor_id = ?',
            [$status, $orderId, $sellerId]
        );

        $orders = Database::query('SELECT * FROM orders WHERE id = ?', [$orderId]);
        Response::success(['order' => $orders[0]]);
    }
}
