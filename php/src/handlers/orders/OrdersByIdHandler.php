<?php
class OrdersByIdHandler
{
    public function handle(array $ctx): void
    {
        if ($ctx['method'] !== 'GET') {
            Response::methodNotAllowed();
        }

        $user    = Auth::requireAuth($ctx['headers']);
        $orderId = Sanitize::integer($ctx['params']['id'] ?? null);
        if (!$orderId) {
            Response::error('INVALID_ID', 'ID inválido');
        }

        $orders = Database::query(
            'SELECT * FROM orders WHERE id = ? AND (comprador_id = ? OR vendedor_id IN (SELECT id FROM sellers WHERE user_id = ?))',
            [$orderId, $user['id'], $user['id']]
        );

        if (empty($orders)) {
            Response::notFound('Pedido não encontrado ou sem permissão');
        }

        $items  = Database::query('SELECT * FROM order_items WHERE order_id = ?', [$orderId]);
        $order  = $orders[0];
        $order['items'] = $items;

        Response::success(['order' => $order]);
    }
}
