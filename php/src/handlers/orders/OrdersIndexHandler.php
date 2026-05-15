<?php
class OrdersIndexHandler
{
    public function handle(array $ctx): void
    {
        $user = Auth::requireAuth($ctx['headers']);

        RateLimit::check(60 * 1000, 20, 'Muitas requisições. Aguarde alguns minutos.');

        if ($ctx['method'] === 'GET') {
            $this->list($ctx, $user);
        } elseif ($ctx['method'] === 'POST') {
            $this->create($ctx, $user);
        } else {
            Response::methodNotAllowed();
        }
    }

    private function list(array $ctx, array $user): void
    {
        $query  = $ctx['query'];
        $page   = max(1, Sanitize::integer($query['page'] ?? 1) ?? 1);
        $limit  = min(100, max(1, Sanitize::integer($query['limit'] ?? 20) ?? 20));
        $offset = ($page - 1) * $limit;

        $count  = Database::query('SELECT COUNT(*) as total FROM orders WHERE comprador_id = ?', [$user['id']]);
        $total  = (int)($count[0]['total'] ?? 0);
        $orders = Database::query(
            'SELECT * FROM orders WHERE comprador_id = ? ORDER BY created_at DESC LIMIT ? OFFSET ?',
            [$user['id'], $limit, $offset]
        );

        Response::success([
            'orders'     => $orders,
            'pagination' => [
                'page'       => $page,
                'limit'      => $limit,
                'total'      => $total,
                'totalPages' => (int)ceil($total / $limit),
            ],
        ]);
    }

    private function create(array $ctx, array $user): void
    {
        $body  = $ctx['body'];
        $items = isset($body['items']) && is_array($body['items']) ? $body['items'] : [];

        if (empty($items)) {
            Response::error('VALIDATION_ERROR', 'Itens do pedido são obrigatórios');
        }

        $shippingTotal          = (float)($body['shippingTotal'] ?? $body['shipping_total'] ?? 0);
        $discountTotal          = (float)($body['discountTotal'] ?? $body['discount_total'] ?? 0);
        $shippingQuoteId        = Sanitize::integer($body['shippingQuoteId'] ?? $body['shipping_quote_id'] ?? null);
        $shippingAddressSnapshot = $body['shippingAddressSnapshot'] ?? $body['shipping_address_snapshot'] ?? null;
        $billingAddressSnapshot  = $body['billingAddressSnapshot']  ?? $body['billing_address_snapshot']  ?? null;

        // Valida e sanitiza os itens
        $sanitizedItems = [];
        foreach ($items as $item) {
            $productId  = Sanitize::integer($item['productId'] ?? $item['product_id'] ?? null);
            $quantidade = max(1, Sanitize::integer($item['quantidade'] ?? 1) ?? 1);
            if (!$productId) {
                Response::error('VALIDATION_ERROR', 'IDs de produtos inválidos');
            }
            $sanitizedItems[] = ['productId' => $productId, 'quantidade' => $quantidade];
        }

        $productIds = array_unique(array_column($sanitizedItems, 'productId'));
        $placeholders = Database::placeholders($productIds);

        $order = Database::transaction(function (PDO $pdo) use (
            $user, $sanitizedItems, $productIds, $placeholders,
            $shippingTotal, $discountTotal, $shippingQuoteId,
            $shippingAddressSnapshot, $billingAddressSnapshot
        ) {
            // Bloqueia produtos
            $stmt = $pdo->prepare(
                "SELECT p.*, s.user_id as seller_user_id FROM products p
                 JOIN sellers s ON p.seller_id = s.id
                 WHERE p.id IN ({$placeholders}) FOR UPDATE"
            );
            $stmt->execute($productIds);
            $lockedProducts = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (count($lockedProducts) !== count($productIds)) {
                throw new RuntimeException('Um ou mais produtos não foram encontrados');
            }

            // Verifica que todos são do mesmo vendedor
            $sellerIds = array_unique(array_column($lockedProducts, 'seller_id'));
            if (count($sellerIds) !== 1) {
                $err = new RuntimeException('No MVP, o carrinho aceita produtos de apenas um vendedor por vez');
                $err->code = 'MULTI_SELLER_NOT_ALLOWED';
                throw $err;
            }

            $sellerId     = $lockedProducts[0]['seller_id'];
            $sellerUserId = $lockedProducts[0]['seller_user_id'];

            if ($user['id'] == $sellerUserId) {
                $err = new RuntimeException('Vendedor não pode comprar seus próprios produtos');
                $err->code = 'FORBIDDEN';
                throw $err;
            }

            $productById = [];
            foreach ($lockedProducts as $p) {
                $productById[$p['id']] = $p;
            }

            // Verifica estoque e calcula subtotal
            $requestedQty = [];
            foreach ($sanitizedItems as $item) {
                $pid = $item['productId'];
                $requestedQty[$pid] = ($requestedQty[$pid] ?? 0) + $item['quantidade'];
            }

            $orderItems = [];
            $subtotal   = 0.0;

            foreach ($sanitizedItems as $item) {
                $p = $productById[$item['productId']];
                if (!$p['publicado']) {
                    $err = new RuntimeException("Produto {$p['id']} não está disponível");
                    $err->code = 'PRODUCT_UNAVAILABLE';
                    throw $err;
                }
                $orderItems[] = [
                    'product'    => $p,
                    'quantidade' => $item['quantidade'],
                    'preco'      => (float)$p['preco'],
                ];
                $subtotal += (float)$p['preco'] * $item['quantidade'];
            }

            foreach ($requestedQty as $pid => $qty) {
                if ((int)$productById[$pid]['estoque'] < $qty) {
                    $err = new RuntimeException("Estoque insuficiente para produto {$pid}");
                    $err->code = 'INSUFFICIENT_STOCK';
                    throw $err;
                }
            }

            $grandTotal = max(0, $subtotal + $shippingTotal - $discountTotal);

            // Insere pedido
            $stmt = $pdo->prepare(
                'INSERT INTO orders (comprador_id, vendedor_id, total, items_subtotal, shipping_total,
                 discount_total, grand_total, selected_shipping_quote_id,
                 shipping_address_snapshot_json, billing_address_snapshot_json)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute([
                $user['id'], $sellerId, $grandTotal, $subtotal, $shippingTotal, $discountTotal, $grandTotal,
                $shippingQuoteId,
                $shippingAddressSnapshot ? json_encode($shippingAddressSnapshot) : null,
                $billingAddressSnapshot  ? json_encode($billingAddressSnapshot)  : null,
            ]);
            $orderId = (int)$pdo->lastInsertId();

            // Insere itens e decrementa estoque
            foreach ($orderItems as $item) {
                $p = $item['product'];
                $stmt = $pdo->prepare(
                    'INSERT INTO order_items (order_id, seller_id, product_id, quantidade,
                     preco_unitario, unit_price, name_snapshot, weight_snapshot, dimension_snapshot_json)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
                );
                $stmt->execute([
                    $orderId, $sellerId, $p['id'], $item['quantidade'],
                    $item['preco'], $item['preco'], $p['nome'], $p['weight_kg'],
                    json_encode([
                        'height_cm' => $p['height_cm'],
                        'width_cm'  => $p['width_cm'],
                        'length_cm' => $p['length_cm'],
                    ]),
                ]);

                $pdo->prepare(
                    'UPDATE products SET estoque = estoque - ? WHERE id = ? AND estoque >= ?'
                )->execute([$item['quantidade'], $p['id'], $item['quantidade']]);
            }

            // Retorna pedido completo
            $orderStmt = $pdo->prepare('SELECT * FROM orders WHERE id = ?');
            $orderStmt->execute([$orderId]);
            $order = $orderStmt->fetch(PDO::FETCH_ASSOC);

            $itemsStmt = $pdo->prepare('SELECT * FROM order_items WHERE order_id = ?');
            $itemsStmt->execute([$orderId]);
            $order['items'] = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);

            return $order;
        });

        Response::success(['order' => $order], 201);
    }
}
