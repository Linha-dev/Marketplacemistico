<?php
class SellersByIdHandler
{
    public function handle(array $ctx): void
    {
        if ($ctx['method'] !== 'GET') {
            Response::methodNotAllowed();
        }

        $id = Sanitize::integer($ctx['params']['id'] ?? null);
        if (!$id) {
            Response::error('INVALID_ID', 'ID inválido');
        }

        $sellers = Database::query(
            'SELECT s.id, s.nome_loja, s.categoria, s.descricao_loja, s.logo_url,
                    s.avaliacao_media, s.total_vendas, s.created_at
             FROM sellers s
             WHERE s.id = ?',
            [$id]
        );

        if (empty($sellers)) {
            Response::notFound('Vendedor não encontrado');
        }

        Response::success(['seller' => $sellers[0]]);
    }
}
