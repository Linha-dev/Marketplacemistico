<?php
class MeHandler
{
    public function handle(array $ctx): void
    {
        if ($ctx['method'] !== 'GET') {
            Response::methodNotAllowed();
        }

        $authUser = Auth::requireAuth($ctx['headers']);

        $users = Database::query(
            'SELECT u.id, u.tipo, u.nome, u.email, u.telefone, u.cpf_cnpj, u.tipo_documento,
                    u.created_at, u.updated_at,
                    s.id as seller_id, s.nome_loja, s.categoria, s.descricao_loja,
                    s.logo_url, s.avaliacao_media, s.total_vendas
             FROM users u
             LEFT JOIN sellers s ON u.id = s.user_id
             WHERE u.id = ?',
            [$authUser['id']]
        );

        if (empty($users)) {
            Response::notFound('Usuário não encontrado');
        }

        $user         = $users[0];
        $user['role'] = $authUser['role'];

        Response::success(['user' => $user]);
    }
}
