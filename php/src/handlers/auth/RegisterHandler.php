<?php
class RegisterHandler
{
    public function handle(array $ctx): void
    {
        if ($ctx['method'] !== 'POST') {
            Response::methodNotAllowed();
        }

        RateLimit::check(60 * 60 * 1000, 10, 'Muitas tentativas de cadastro. Aguarde alguns minutos.');

        $body = $ctx['body'];

        $tipo          = Sanitize::string($body['tipo'] ?? '');
        $nome          = Sanitize::string($body['nome'] ?? '');
        $email         = Sanitize::email($body['email'] ?? '');
        $senha         = $body['senha'] ?? '';
        $telefone      = Sanitize::phone($body['telefone'] ?? '');
        $cpf_cnpj      = $body['cpf_cnpj'] ?? '';
        $nomeLoja      = Sanitize::string($body['nomeLoja'] ?? '');
        $categoria     = Sanitize::string($body['categoria'] ?? '');
        $descricaoLoja = Sanitize::string($body['descricaoLoja'] ?? '');

        if (!$tipo || !$nome || !$email || !$senha) {
            Response::error('VALIDATION_ERROR', 'Campos obrigatórios faltando');
        }

        if (strlen($nome) < 3) {
            Response::error('VALIDATION_ERROR', 'Nome deve ter ao menos 3 caracteres');
        }

        if (!in_array($tipo, ['cliente', 'vendedor'], true)) {
            Response::error('VALIDATION_ERROR', 'Tipo deve ser "cliente" ou "vendedor"');
        }

        $emailValidation = Sanitize::validateEmail($email);
        if (!$emailValidation['ok']) {
            Response::error('VALIDATION_ERROR', 'Formato de e-mail inválido');
        }

        $passwordValidation = Sanitize::validatePassword($senha);
        if (!$passwordValidation['ok']) {
            Response::error('VALIDATION_ERROR', $passwordValidation['reason']);
        }

        $phoneValidation = Sanitize::validatePhone($telefone);
        if (!$phoneValidation['ok']) {
            Response::error('VALIDATION_ERROR', $phoneValidation['reason']);
        }

        $documentValidation = Sanitize::validateCpfCnpj($cpf_cnpj);
        if (!$documentValidation['ok']) {
            Response::error('VALIDATION_ERROR', $documentValidation['reason']);
        }

        if ($tipo === 'vendedor' && (!$nomeLoja || !$categoria)) {
            Response::error('VALIDATION_ERROR', 'Nome da loja e categoria são obrigatórios para vendedor');
        }

        // Unicidade
        if (!empty(Database::query('SELECT id FROM users WHERE email = ? LIMIT 1', [$emailValidation['value']]))) {
            Response::error('EMAIL_TAKEN', 'Email já cadastrado');
        }

        if (!empty(Database::query('SELECT id FROM users WHERE telefone = ? LIMIT 1', [$phoneValidation['value']]))) {
            Response::error('PHONE_TAKEN', 'Telefone já cadastrado');
        }

        if (!empty(Database::query('SELECT id FROM users WHERE cpf_cnpj = ? LIMIT 1', [$documentValidation['value']]))) {
            Response::error('DOCUMENT_TAKEN', $documentValidation['type'] . ' já cadastrado');
        }

        if ($tipo === 'vendedor') {
            if (!empty(Database::query('SELECT id FROM sellers WHERE LOWER(nome_loja) = LOWER(?) LIMIT 1', [$nomeLoja]))) {
                Response::error('STORE_NAME_TAKEN', 'Nome da loja já cadastrado');
            }
        }

        $senhaHash = password_hash($senha, PASSWORD_BCRYPT, ['cost' => 10]);

        $user = Database::transaction(function (PDO $pdo) use (
            $tipo, $nome, $emailValidation, $senhaHash, $phoneValidation, $documentValidation,
            $nomeLoja, $categoria, $descricaoLoja
        ) {
            $stmt = $pdo->prepare(
                'INSERT INTO users (tipo, nome, email, senha_hash, telefone, cpf_cnpj, tipo_documento)
                 VALUES (?, ?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute([
                $tipo,
                $nome,
                $emailValidation['value'],
                $senhaHash,
                $phoneValidation['value'],
                $documentValidation['value'],
                $documentValidation['type'],
            ]);
            $userId = (int)$pdo->lastInsertId();
            $users  = $pdo->prepare('SELECT * FROM users WHERE id = ?');
            $users->execute([$userId]);
            $user = $users->fetch(PDO::FETCH_ASSOC);

            if ($tipo === 'vendedor') {
                $stmt2 = $pdo->prepare(
                    'INSERT INTO sellers (user_id, nome_loja, categoria, descricao_loja) VALUES (?, ?, ?, ?)'
                );
                $stmt2->execute([$userId, $nomeLoja, $categoria, $descricaoLoja ?: '']);
                $user['seller_id']     = (int)$pdo->lastInsertId();
                $user['nomeLoja']      = $nomeLoja;
                $user['categoria']     = $categoria;
                $user['descricaoLoja'] = $descricaoLoja ?: '';
            }

            return $user;
        });

        $secret = Config::require('JWT_SECRET');
        $role   = RBAC::resolveUserRole($user);
        $token  = Auth::generateToken(
            ['id' => $user['id'], 'email' => $user['email'], 'tipo' => $user['tipo'], 'role' => $role],
            $secret,
            7200
        );

        Response::success([
            'message' => 'Usuário criado com sucesso',
            'token'   => $token,
            'user'    => [
                'id'            => $user['id'],
                'tipo'          => $user['tipo'],
                'role'          => $role,
                'nome'          => $user['nome'],
                'email'         => $user['email'],
                'telefone'      => $user['telefone'],
                'cpf_cnpj'      => $user['cpf_cnpj'],
                'seller_id'     => $user['seller_id'] ?? null,
                'nomeLoja'      => $user['nomeLoja'] ?? null,
                'categoria'     => $user['categoria'] ?? null,
                'descricaoLoja' => $user['descricaoLoja'] ?? null,
            ],
        ], 201);
    }
}
