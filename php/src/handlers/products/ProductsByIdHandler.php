<?php
class ProductsByIdHandler
{
    public function handle(array $ctx): void
    {
        $id = Sanitize::integer($ctx['params']['id'] ?? null);
        if (!$id) {
            Response::error('INVALID_ID', 'ID inválido');
        }

        if ($ctx['method'] === 'GET') {
            $this->get($id);
        } elseif ($ctx['method'] === 'PUT') {
            $user = Auth::requireVendedor($ctx['headers']);
            $this->update($ctx, $id, $user);
        } elseif ($ctx['method'] === 'DELETE') {
            $user = Auth::requireVendedor($ctx['headers']);
            $this->delete($id, $user);
        } else {
            Response::methodNotAllowed();
        }
    }

    private function get(int $id): void
    {
        $products = Database::query(
            'SELECT p.*, s.nome_loja, s.avaliacao_media
             FROM products p JOIN sellers s ON p.seller_id = s.id
             WHERE p.id = ?',
            [$id]
        );
        if (empty($products)) {
            Response::notFound('Produto não encontrado');
        }
        Response::success(['product' => $products[0]]);
    }

    private function update(array $ctx, int $id, array $user): void
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

        $stmt = Database::execute(
            'UPDATE products SET nome=?, categoria=?, descricao=?, preco=?, estoque=?, imagem_url=?,
             weight_kg=?, height_cm=?, width_cm=?, length_cm=?, insurance_value=?, updated_at=CURRENT_TIMESTAMP
             WHERE id=? AND seller_id=?',
            [
                $nome, $categoria, $descricao, $preco, $estoque, $imageValidation['value'],
                $d['weightKg'], $d['heightCm'], $d['widthCm'], $d['lengthCm'], $d['insuranceValue'],
                $id, $sellerId,
            ]
        );

        if ($stmt->rowCount() === 0) {
            Response::notFound('Produto não encontrado ou sem permissão');
        }

        $products = Database::query('SELECT * FROM products WHERE id = ?', [$id]);
        Response::success(['product' => $products[0]]);
    }

    private function delete(int $id, array $user): void
    {
        $sellers = Database::query('SELECT id FROM sellers WHERE user_id = ?', [$user['id']]);
        if (empty($sellers)) {
            Response::notFound('Vendedor não encontrado');
        }
        $sellerId = $sellers[0]['id'];

        $stmt = Database::execute(
            'DELETE FROM products WHERE id = ? AND seller_id = ?',
            [$id, $sellerId]
        );

        if ($stmt->rowCount() === 0) {
            Response::notFound('Produto não encontrado ou sem permissão');
        }

        Response::success(['message' => 'Produto deletado']);
    }
}
