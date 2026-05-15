<?php
class AddressesHandler
{
    public function handle(array $ctx): void
    {
        $user = Auth::requireAuth($ctx['headers']);

        if ($ctx['method'] === 'GET') {
            $this->list($user);
        } elseif ($ctx['method'] === 'POST') {
            $this->create($ctx, $user);
        } else {
            Response::methodNotAllowed();
        }
    }

    private function list(array $user): void
    {
        $addresses = Database::query(
            'SELECT * FROM addresses WHERE user_id = ? ORDER BY is_default DESC, created_at DESC',
            [$user['id']]
        );
        Response::success(['addresses' => $addresses]);
    }

    private function create(array $ctx, array $user): void
    {
        $body       = $ctx['body'];
        $cep        = Sanitize::string($body['cep'] ?? '');
        $rua        = Sanitize::string($body['rua'] ?? '');
        $numero     = Sanitize::string($body['numero'] ?? '');
        $complemento = Sanitize::string($body['complemento'] ?? '');
        $bairro     = Sanitize::string($body['bairro'] ?? '');
        $cidade     = Sanitize::string($body['cidade'] ?? '');
        $estado     = Sanitize::string($body['estado'] ?? '');
        $isDefault  = Sanitize::boolean($body['is_default'] ?? false);

        if (!$cep || !$rua || !$numero || !$bairro || !$cidade || !$estado) {
            Response::error('VALIDATION_ERROR', 'Campos obrigatórios: cep, rua, numero, bairro, cidade, estado');
        }

        if ($isDefault) {
            Database::execute('UPDATE addresses SET is_default = 0 WHERE user_id = ?', [$user['id']]);
        }

        Database::execute(
            'INSERT INTO addresses (user_id, cep, rua, numero, complemento, bairro, cidade, estado, is_default)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [$user['id'], $cep, $rua, $numero, $complemento, $bairro, $cidade, $estado, (int)$isDefault]
        );
        $id      = (int)Database::getConnection()->lastInsertId();
        $address = Database::query('SELECT * FROM addresses WHERE id = ?', [$id]);

        Response::success(['address' => $address[0]], 201);
    }
}
