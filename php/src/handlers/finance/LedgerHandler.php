<?php
class LedgerHandler
{
    public function handle(array $ctx): void
    {
        if ($ctx['method'] !== 'GET') {
            Response::methodNotAllowed();
        }

        $user    = Auth::requireAuth($ctx['headers']);
        $orderId = Sanitize::integer($ctx['params']['orderId'] ?? $ctx['query']['orderId'] ?? null);

        if (!$orderId) {
            Response::error('VALIDATION_ERROR', 'orderId obrigatório');
        }

        // Verifica que o usuário tem acesso ao pedido
        $orders = Database::query(
            'SELECT o.id, o.comprador_id, o.vendedor_id, o.total, o.grand_total, o.status,
                    s.user_id as seller_user_id
             FROM orders o
             JOIN sellers s ON s.id = o.vendedor_id
             WHERE o.id = ? AND (o.comprador_id = ? OR s.user_id = ?)',
            [$orderId, $user['id'], $user['id']]
        );

        if (empty($orders)) {
            Response::notFound('Pedido não encontrado ou sem permissão');
        }

        // Busca entradas do ledger financeiro
        $entries = Database::query(
            'SELECT * FROM finance_ledger WHERE order_id = ? ORDER BY created_at ASC',
            [$orderId]
        );

        // Resumo de splits
        $splits = Database::query(
            'SELECT ps.*, s.nome_loja
             FROM payment_splits ps
             JOIN payments p ON p.id = ps.payment_id
             JOIN sellers s ON s.id = ps.seller_id
             WHERE p.order_id = ?',
            [$orderId]
        );

        Response::success([
            'order'   => $orders[0],
            'entries' => $entries,
            'splits'  => $splits,
        ]);
    }
}
