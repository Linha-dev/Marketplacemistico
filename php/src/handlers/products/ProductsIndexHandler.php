<?php
class ProductsIndexHandler
{
    public function handle(array $ctx): void
    {
        if ($ctx['method'] === 'POST') {
            $user = Auth::requireVendedor($ctx['headers']);
            $this->create($ctx, $user);
        } elseif ($ctx['method'] === 'GET') {
            $this->list($ctx);
        } else {
            Response::methodNotAllowed();
        }
    }

    private function list(array $ctx): void
    {
        $query     = $ctx['query'];
        $page      = max(1, Sanitize::integer($query['page'] ?? 1) ?? 1);
        $limit     = min(100, max(1, Sanitize::integer($query['limit'] ?? 20) ?? 20));
        $offset    = ($page - 1) * $limit;
        $categoria = Sanitize::string($query['categoria'] ?? '');
        $sellerId  = Sanitize::integer($query['sellerId'] ?? null);
        $search    = Sanitize::string($query['search'] ?? '');

        $where  = 'WHERE p.publicado = 1';
        $params = [];

        if ($categoria && $categoria !== 'Todos') {
            $where    .= ' AND p.categoria = ?';
            $params[]  = $categoria;
        }
        if ($sellerId) {
            $where    .= ' AND s.id = ?';
            $params[]  = $sellerId;
        }
        if ($search) {
            $where    .= ' AND (p.nome LIKE ? OR p.descricao LIKE ?)';
            $params[]  = '%' . $search . '%';
            $params[]  = '%' . $search . '%';
        }

        $baseQuery = "FROM products p JOIN sellers s ON p.seller_id = s.id {$where}";

        $countRows = Database::query("SELECT COUNT(*) as total {$baseQuery}", $params);
        $total     = (int)($countRows[0]['total'] ?? 0);

        $products = Database::query(
            "SELECT p.*, s.nome_loja, s.avaliacao_media {$baseQuery}
             ORDER BY p.created_at DESC LIMIT ? OFFSET ?",
            array_merge($params, [$limit, $offset])
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

    private function create(array $ctx, array $user): void
    {
        $body = $ctx['body'];

        $sellers = Database::query('SELECT id FROM sellers WHERE user_id = ?', [$user['id']]);
        if (empty($sellers)) {
            Response::notFound('Vendedor não encontrado');
        }
        $sellerId = $sellers[0]['id'];

        $nome      = Sanitize::string($body['nome'] ?? '');
        $categoria = Sanitize::string($body['categoria'] ?? '');
        $descricao = Sanitize::string($body['descricao'] ?? '');
        $preco     = Sanitize::decimalPositive($body['preco'] ?? null);
        $estoque   = max(0, Sanitize::integer($body['estoque'] ?? 0) ?? 0);
        $imagemUrl = $body['imagem_url'] ?? '';

        if (!$nome || !$categoria || $preco === null) {
            Response::error('VALIDATION_ERROR', 'Campos obrigatórios faltando (nome, categoria, preco)');
        }

        $imageValidation = Sanitize::validateImageUrl($imagemUrl);
        if (!$imageValidation['ok']) {
            Response::error('VALIDATION_ERROR', $imageValidation['reason']);
        }

        $dimValidation = Sanitize::validateDimensions($body);
        if (!$dimValidation['ok']) {
            Response::error('VALIDATION_ERROR', $dimValidation['reason']);
        }
        $d = $dimValidation['value'];

        Database::execute(
            'INSERT INTO products (seller_id, nome, categoria, descricao, preco, estoque, imagem_url,
             weight_kg, height_cm, width_cm, length_cm, insurance_value)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $sellerId, $nome, $categoria, $descricao, $preco, $estoque,
                $imageValidation['value'],
                $d['weightKg'], $d['heightCm'], $d['widthCm'], $d['lengthCm'], $d['insuranceValue'],
            ]
        );
        $id = Database::getConnection()->lastInsertId();
        $products = Database::query('SELECT * FROM products WHERE id = ?', [(int)$id]);

        Response::success(['product' => $products[0]], 201);
    }
}
