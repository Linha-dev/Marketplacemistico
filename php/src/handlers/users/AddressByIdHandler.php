<?php
class AddressByIdHandler
{
    public function handle(array $ctx): void
    {
        $user = Auth::requireAuth($ctx['headers']);
        $id   = Sanitize::integer($ctx['params']['id'] ?? null);
        if (!$id) {
            Response::error('INVALID_ID', 'ID inválido');
        }

        if ($ctx['method'] === 'PUT') {
            $this->update($ctx, $user, $id);
        } elseif ($ctx['method'] === 'DELETE') {
            $this->delete($user, $id);
        } else {
            Response::methodNotAllowed();
        }
    }

    private function update(array $ctx, array $user, int $id): void
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

        $stmt = Database::execute(
            'UPDATE addresses SET cep=?, rua=?, numero=?, complemento=?, bairro=?, cidade=?, estado=?, is_default=?
             WHERE id=? AND user_id=?',
            [$cep, $rua, $numero, $complemento, $bairro, $cidade, $estado, (int)$isDefault, $id, $user['id']]
        );

        if ($stmt->rowCount() === 0) {
            Response::notFound('Endereço não encontrado ou sem permissão');
        }

        $address = Database::query('SELECT * FROM addresses WHERE id = ?', [$id]);
        Response::success(['address' => $address[0]]);
    }

    private function delete(array $user, int $id): void
    {
        $stmt = Database::execute(
            'DELETE FROM addresses WHERE id = ? AND user_id = ?',
            [$id, $user['id']]
        );

        if ($stmt->rowCount() === 0) {
            Response::notFound('Endereço não encontrado ou sem permissão');
        }

        Response::success(['message' => 'Endereço removido']);
    }
}
