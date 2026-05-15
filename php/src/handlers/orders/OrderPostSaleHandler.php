<?php
class OrderPostSaleHandler
{
    private const CANCEL_ALLOWED = ['pendente', 'confirmado', 'em_preparacao'];
    private const RETURN_ALLOWED = ['entregue'];

    public function handle(array $ctx): void
    {
        if ($ctx['method'] !== 'POST') {
            Response::methodNotAllowed();
        }

        $user    = Auth::requireAuth($ctx['headers']);
        $orderId = Sanitize::integer($ctx['params']['id'] ?? null);
        if (!$orderId) {
            Response::error('VALIDATION_ERROR', 'ID do pedido inválido');
        }

        $action = Sanitize::string($ctx['body']['action'] ?? '');
        $reason = Sanitize::string($ctx['body']['reason'] ?? '');

        if (!in_array($action, ['cancel', 'return_request'], true)) {
            Response::error('VALIDATION_ERROR', 'action deve ser cancel ou return_request');
        }

        $result = Database::transaction(function (PDO $pdo) use ($user, $orderId, $action, $reason) {
            $stmt = $pdo->prepare('SELECT * FROM orders WHERE id = ? FOR UPDATE');
            $stmt->execute([$orderId]);
            $order = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$order) {
                throw new RuntimeException('Pedido não encontrado');
            }

            if ($order['comprador_id'] != $user['id']) {
                $err = new RuntimeException('Apenas o comprador pode solicitar cancelamento/devolução');
                $err->code = 'FORBIDDEN';
                throw $err;
            }

            $prevStatus         = $order['status'];
            $prevShippingStatus = $order['shipping_status'];
            $nextStatus         = $order['status'];
            $nextShippingStatus = $order['shipping_status'];

            if ($action === 'cancel') {
                if (!in_array($order['status'], self::CANCEL_ALLOWED, true)) {
                    $err = new RuntimeException('Cancelamento permitido apenas antes do envio');
                    $err->code = 'CANCEL_NOT_ALLOWED';
                    throw $err;
                }
                $nextStatus         = 'cancelado';
                $nextShippingStatus = 'cancelled';
            }

            if ($action === 'return_request') {
                if (!in_array($order['status'], self::RETURN_ALLOWED, true)) {
                    $err = new RuntimeException('Devolução permitida apenas para pedido entregue');
                    $err->code = 'RETURN_NOT_ALLOWED';
                    throw $err;
                }
                $nextStatus         = 'devolvido';
                $nextShippingStatus = 'returned';
            }

            $pdo->prepare(
                'UPDATE orders SET status = ?, shipping_status = ? WHERE id = ?'
            )->execute([$nextStatus, $nextShippingStatus, $orderId]);

            $orderStmt = $pdo->prepare('SELECT * FROM orders WHERE id = ?');
            $orderStmt->execute([$orderId]);
            $updatedOrder = $orderStmt->fetch(PDO::FETCH_ASSOC);

            return [
                'order'  => $updatedOrder,
                'refund' => null,
                'action' => $action,
            ];
        });

        Response::success($result);
    }
}
