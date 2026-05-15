<?php
class ShippingQuoteHandler
{
    public function handle(array $ctx): void
    {
        if ($ctx['method'] !== 'POST') {
            Response::methodNotAllowed();
        }

        $user = Auth::requireAuth($ctx['headers']);
        $body = $ctx['body'];

        $sellerId             = Sanitize::integer($body['seller_id'] ?? null);
        $destinationPostalCode = preg_replace('/\D/', '', Sanitize::string($body['destination_postal_code'] ?? ''));
        $items                = is_array($body['items'] ?? null) ? $body['items'] : [];
        $cartId               = Sanitize::string($body['cart_id'] ?? 'cart_' . $user['id']);

        if (!$sellerId || strlen($destinationPostalCode) !== 8 || empty($items)) {
            Response::error('VALIDATION_ERROR', 'seller_id, destination_postal_code e items são obrigatórios');
        }

        $productIds = [];
        foreach ($items as $item) {
            $pid = Sanitize::integer($item['product_id'] ?? $item['productId'] ?? null);
            if (!$pid) {
                Response::error('VALIDATION_ERROR', 'IDs de produtos inválidos');
            }
            $productIds[] = $pid;
        }

        // Busca perfil de envio do vendedor
        $sellerOriginRows = Database::query(
            'SELECT * FROM seller_shipping_profiles WHERE seller_id = ? LIMIT 1',
            [$sellerId]
        );
        if (empty($sellerOriginRows)) {
            Response::error('VALIDATION_ERROR', 'Seller sem origem de envio configurada');
        }

        // Busca produtos
        $placeholders = Database::placeholders($productIds);
        $products     = Database::query(
            "SELECT id, seller_id, preco, weight_kg, height_cm, width_cm, length_cm
             FROM products WHERE id IN ({$placeholders})",
            $productIds
        );

        if (count($products) !== count($productIds)) {
            Response::notFound('Um ou mais produtos não encontrados');
        }

        foreach ($products as $p) {
            if ($p['seller_id'] != $sellerId) {
                Response::error('MULTI_SELLER_NOT_ALLOWED', 'Itens devem pertencer ao mesmo vendedor');
            }
            if (!$p['weight_kg'] || !$p['height_cm'] || !$p['width_cm'] || !$p['length_cm']) {
                Response::error('VALIDATION_ERROR', 'Todos os produtos precisam de peso e dimensões para cotação');
            }
        }

        // Agrega dimensões
        $totalWeight = array_reduce($products, fn($s, $p) => $s + (float)$p['weight_kg'], 0.0);
        $packageInfo = [
            'weight_kg'       => max(0.1, $totalWeight),
            'height_cm'       => max(array_column($products, 'height_cm')),
            'width_cm'        => max(array_column($products, 'width_cm')),
            'length_cm'       => max(array_column($products, 'length_cm')),
            'insurance_value' => array_reduce($products, fn($s, $p) => $s + (float)$p['preco'], 0.0),
        ];

        $payload    = MelhorEnvioClient::buildQuotePayload($sellerOriginRows[0], $destinationPostalCode, $packageInfo);
        $rawOptions = MelhorEnvioClient::quoteShipment($payload);
        $options    = MelhorEnvioClient::mapQuoteResponse($rawOptions);

        // Salva cotações no banco
        $savedOptions = [];
        $driver = Database::driver();
        foreach ($options as $option) {
            if ($driver === 'pgsql') {
                $rows = Database::query(
                    "INSERT INTO shipping_quotes (cart_id, buyer_id, seller_id, service_id, service_name, carrier_name,
                     price, custom_price, delivery_time, raw_response_json, expires_at)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?::jsonb, NOW() + INTERVAL '30 minutes')
                     RETURNING *",
                    [
                        $cartId, $user['id'], $sellerId, $option['serviceId'], $option['serviceName'],
                        $option['carrierName'], $option['price'], $option['customPrice'],
                        $option['deliveryTime'], json_encode($option['raw']),
                    ]
                );
                if (!empty($rows)) {
                    $savedOptions[] = $rows[0];
                }
            } else {
                Database::execute(
                    'INSERT INTO shipping_quotes (cart_id, buyer_id, seller_id, service_id, service_name, carrier_name,
                     price, custom_price, delivery_time, raw_response_json, expires_at)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL 30 MINUTE))',
                    [
                        $cartId, $user['id'], $sellerId, $option['serviceId'], $option['serviceName'],
                        $option['carrierName'], $option['price'], $option['customPrice'],
                        $option['deliveryTime'], json_encode($option['raw']),
                    ]
                );
                $id = (int)Database::getConnection()->lastInsertId();
                $rows = Database::query('SELECT * FROM shipping_quotes WHERE id = ?', [$id]);
                if (!empty($rows)) {
                    $savedOptions[] = $rows[0];
                }
            }
        }

        Response::success([
            'cartId'          => $cartId,
            'packageInfo'     => $packageInfo,
            'quotes'          => $savedOptions,
            'providerPayload' => $payload,
        ]);
    }
}
