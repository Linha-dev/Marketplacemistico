<?php
class ProductPublishHandler
{
    public function handle(array $ctx): void
    {
        if ($ctx['method'] !== 'PATCH') {
            Response::methodNotAllowed();
        }

        $user = Auth::requireVendedor($ctx['headers']);
        $id   = Sanitize::integer($ctx['params']['id'] ?? null);
        if (!$id) {
            Response::error('INVALID_ID', 'ID inválido');
        }

        $sellers = Database::query('SELECT id FROM sellers WHERE user_id = ?', [$user['id']]);
        if (empty($sellers)) {
            Response::notFound('Vendedor não encontrado');
        }
        $sellerId = $sellers[0]['id'];

        $current = Database::query(
            'SELECT publicado, weight_kg, height_cm, width_cm, length_cm
             FROM products WHERE id = ? AND seller_id = ?',
            [$id, $sellerId]
        );
        if (empty($current)) {
            Response::notFound('Produto não encontrado ou sem permissão');
        }

        $p          = $current[0];
        $novoEstado = !(bool)$p['publicado'];

        if ($novoEstado && (!$p['weight_kg'] || !$p['height_cm'] || !$p['width_cm'] || !$p['length_cm'])) {
            Response::error('VALIDATION_ERROR', 'Peso e dimensões são obrigatórios para publicar o produto');
        }

        Database::execute(
            'UPDATE products SET publicado = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ? AND seller_id = ?',
            [(int)$novoEstado, $id, $sellerId]
        );

        $products = Database::query('SELECT * FROM products WHERE id = ?', [$id]);
        Response::success(['product' => $products[0]]);
    }
}
