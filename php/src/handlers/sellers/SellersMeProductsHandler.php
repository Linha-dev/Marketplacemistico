<?php
class SellersMeProductsHandler
{
    public function handle(array $ctx): void
    {
        if ($ctx['method'] !== 'GET') {
            Response::methodNotAllowed();
        }

        $user = Auth::requireVendedor($ctx['headers']);

        $sellers = Database::query('SELECT id FROM sellers WHERE user_id = ?', [$user['id']]);
        if (empty($sellers)) {
            Response::notFound('Vendedor não encontrado');
        }
        $sellerId = $sellers[0]['id'];

        $query  = $ctx['query'];
        $page   = max(1, Sanitize::integer($query['page'] ?? 1) ?? 1);
        $limit  = min(100, max(1, Sanitize::integer($query['limit'] ?? 20) ?? 20));
        $offset = ($page - 1) * $limit;

        $count = Database::query(
            'SELECT COUNT(*) as total FROM products WHERE seller_id = ?',
            [$sellerId]
        );
        $total    = (int)($count[0]['total'] ?? 0);
        $products = Database::query(
            'SELECT * FROM products WHERE seller_id = ? ORDER BY created_at DESC LIMIT ? OFFSET ?',
            [$sellerId, $limit, $offset]
        );

        Response::success([
            'products'   => $products,
            'pagination' => [
                'page'       => $page,
                'limit'      => $limit,
                'total'      => $total,
                'totalPages' => (int)ceil($total / $limit),
            ],
        ]);
    }
}
