<?php
class UpgradeToVendorHandler
{
    public function handle(array $ctx): void
    {
        if ($ctx['method'] !== 'POST') {
            Response::methodNotAllowed();
        }

        $user = Auth::requireAuth($ctx['headers']);
        $body = $ctx['body'];

        // Verifica tipo atual
        $users = Database::query('SELECT tipo FROM users WHERE id = ?', [$user['id']]);
        if (empty($users)) {
            Response::notFound('Usuário não encontrado');
        }

        $currentTipo = $users[0]['tipo'];
        if ($currentTipo === 'vendedor') {
            Response::error('ALREADY_VENDOR', 'Você já é um vendedor');
        }
        if ($currentTipo !== 'cliente') {
            Response::error('INVALID_TYPE', 'Apenas clientes podem se tornar vendedores');
        }

        $nomeLoja      = Sanitize::string($body['nome_loja'] ?? '');
        $categoria     = Sanitize::string($body['categoria'] ?? '');
        $descricaoLoja = Sanitize::string($body['descricao_loja'] ?? '');
        $cpfCnpj       = $body['cpf_cnpj'] ?? '';

        if (!$nomeLoja || !$categoria || !$cpfCnpj) {
            Response::error('VALIDATION_ERROR', 'Nome da loja, categoria e CPF/CNPJ são obrigatórios');
        }

        $documentValidation = Sanitize::validateCpfCnpj($cpfCnpj);
        if (!$documentValidation['ok']) {
            Response::error('VALIDATION_ERROR', $documentValidation['reason']);
        }

        // Unicidade de documento
        $existingDoc = Database::query(
            'SELECT id FROM users WHERE cpf_cnpj = ? AND id != ? LIMIT 1',
            [$documentValidation['value'], $user['id']]
        );
        if (!empty($existingDoc)) {
            Response::error('DOCUMENT_TAKEN', $documentValidation['type'] . ' já cadastrado');
        }

        // Unicidade de nome de loja
        $existingStore = Database::query(
            'SELECT id FROM sellers WHERE LOWER(nome_loja) = LOWER(?) LIMIT 1',
            [$nomeLoja]
        );
        if (!empty($existingStore)) {
            Response::error('STORE_NAME_TAKEN', 'Nome da loja já cadastrado');
        }

        Database::transaction(function (PDO $pdo) use ($user, $nomeLoja, $categoria, $descricaoLoja, $documentValidation) {
            $pdo->prepare(
                'UPDATE users SET tipo = ?, cpf_cnpj = ?, tipo_documento = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?'
            )->execute(['vendedor', $documentValidation['value'], $documentValidation['type'], $user['id']]);

            $pdo->prepare(
                'INSERT INTO sellers (user_id, nome_loja, categoria, descricao_loja) VALUES (?, ?, ?, ?)'
            )->execute([$user['id'], $nomeLoja, $categoria, $descricaoLoja ?: '']);
        });

        // Busca dados atualizados
        $updatedUsers = Database::query(
            'SELECT u.id, u.tipo, u.nome, u.email, u.telefone, u.cpf_cnpj, u.tipo_documento,
                    s.id as seller_id, s.nome_loja, s.categoria, s.descricao_loja
             FROM users u
             LEFT JOIN sellers s ON u.id = s.user_id
             WHERE u.id = ?',
            [$user['id']]
        );
        $updatedUser = $updatedUsers[0];

        $secret   = Config::require('JWT_SECRET');
        $role     = RBAC::resolveUserRole($updatedUser);
        $newToken = Auth::generateToken(
            ['id' => $updatedUser['id'], 'email' => $updatedUser['email'], 'tipo' => $updatedUser['tipo'], 'role' => $role],
            $secret,
            7200
        );

        Response::success([
            'message' => 'Parabéns! Você agora é um vendedor!',
            'token'   => $newToken,
            'user'    => $updatedUser,
        ]);
    }
}
