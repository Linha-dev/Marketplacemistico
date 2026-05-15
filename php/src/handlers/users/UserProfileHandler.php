<?php
class UserProfileHandler
{
    public function handle(array $ctx): void
    {
        $user = Auth::requireAuth($ctx['headers']);

        if ($ctx['method'] === 'GET') {
            $this->get($user);
        } elseif ($ctx['method'] === 'PUT') {
            $this->update($ctx, $user);
        } else {
            Response::methodNotAllowed();
        }
    }

    private function get(array $user): void
    {
        $users = Database::query(
            'SELECT u.id, u.tipo, u.nome, u.email, u.telefone, u.cpf_cnpj, u.tipo_documento,
                    u.created_at, u.updated_at,
                    s.id as seller_id, s.nome_loja, s.categoria, s.descricao_loja
             FROM users u
             LEFT JOIN sellers s ON u.id = s.user_id
             WHERE u.id = ?',
            [$user['id']]
        );
        if (empty($users)) {
            Response::notFound('Usuário não encontrado');
        }
        Response::success(['user' => $users[0]]);
    }

    private function update(array $ctx, array $user): void
    {
        $body     = $ctx['body'];
        $nome     = Sanitize::string($body['nome'] ?? '');
        $telefone = Sanitize::phone($body['telefone'] ?? '');

        if (!$nome) {
            Response::error('VALIDATION_ERROR', 'Nome é obrigatório');
        }

        if ($telefone) {
            $phoneValidation = Sanitize::validatePhone($telefone);
            if (!$phoneValidation['ok']) {
                Response::error('VALIDATION_ERROR', $phoneValidation['reason']);
            }
            // Verifica unicidade
            $existing = Database::query(
                'SELECT id FROM users WHERE telefone = ? AND id != ? LIMIT 1',
                [$telefone, $user['id']]
            );
            if (!empty($existing)) {
                Response::error('PHONE_TAKEN', 'Telefone já cadastrado');
            }
        }

        Database::execute(
            'UPDATE users SET nome = ?, telefone = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?',
            [$nome, $telefone ?: null, $user['id']]
        );

        $this->get($user);
    }
}
