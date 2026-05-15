<?php
class LoginHandler
{
    public function handle(array $ctx): void
    {
        if ($ctx['method'] !== 'POST') {
            Response::methodNotAllowed();
        }

        RateLimit::check(5 * 60 * 1000, 10, 'Muitas tentativas de login. Aguarde alguns minutos.');

        $body = $ctx['body'];

        $email = Sanitize::email($body['email'] ?? '');
        $senha = $body['senha'] ?? '';

        if (!$email || !$senha) {
            Response::error('VALIDATION_ERROR', 'Email e senha são obrigatórios');
        }

        $users = Database::query(
            'SELECT u.*, s.id as seller_id, s.nome_loja, s.categoria, s.descricao_loja
             FROM users u
             LEFT JOIN sellers s ON u.id = s.user_id
             WHERE u.email = ?',
            [$email]
        );

        if (empty($users)) {
            Response::error('INVALID_CREDENTIALS', 'Email ou senha incorretos', 401);
        }

        $user = $users[0];

        if (!password_verify($senha, $user['senha_hash'] ?? '')) {
            Response::error('INVALID_CREDENTIALS', 'Email ou senha incorretos', 401);
        }

        $secret = Config::require('JWT_SECRET');
        $role   = RBAC::resolveUserRole($user);
        $token  = Auth::generateToken(
            ['id' => $user['id'], 'email' => $user['email'], 'tipo' => $user['tipo'], 'role' => $role],
            $secret,
            7200
        );

        $endereco = null;
        if ($user['tipo'] === 'cliente') {
            $addresses = Database::query(
                'SELECT * FROM addresses WHERE user_id = ? AND is_default = 1 LIMIT 1',
                [$user['id']]
            );
            if (!empty($addresses)) {
                $endereco = $addresses[0];
            }
        }

        Response::success([
            'token' => $token,
            'user'  => [
                'id'            => $user['id'],
                'tipo'          => $user['tipo'],
                'role'          => $role,
                'nome'          => $user['nome'],
                'email'         => $user['email'],
                'telefone'      => $user['telefone'],
                'cpf_cnpj'      => $user['cpf_cnpj'],
                'seller_id'     => $user['seller_id'],
                'nomeLoja'      => $user['nome_loja'],
                'categoria'     => $user['categoria'],
                'descricaoLoja' => $user['descricao_loja'],
                'endereco'      => $endereco,
            ],
        ]);
    }
}
