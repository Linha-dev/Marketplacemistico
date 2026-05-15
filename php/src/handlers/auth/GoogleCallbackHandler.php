<?php
/**
 * Google OAuth Callback - Troca o código pelo token e faz login/registro do usuário.
 */
class GoogleCallbackHandler
{
    public function handle(array $ctx): void
    {
        $code = $ctx['query']['code'] ?? '';
        if (!$code) {
            Response::error('VALIDATION_ERROR', 'Código OAuth ausente');
        }

        $clientId     = Config::require('GOOGLE_CLIENT_ID');
        $clientSecret = Config::require('GOOGLE_CLIENT_SECRET');
        $redirectUri  = Config::require('GOOGLE_REDIRECT_URI');

        // Troca código por access token
        $tokenResponse = $this->httpPost('https://oauth2.googleapis.com/token', [
            'code'          => $code,
            'client_id'     => $clientId,
            'client_secret' => $clientSecret,
            'redirect_uri'  => $redirectUri,
            'grant_type'    => 'authorization_code',
        ]);

        if (empty($tokenResponse['access_token'])) {
            Response::error('OAUTH_ERROR', 'Falha ao trocar código OAuth', 500);
        }

        // Busca dados do usuário no Google
        $userInfo = $this->httpGet(
            'https://www.googleapis.com/oauth2/v3/userinfo',
            $tokenResponse['access_token']
        );

        $googleId = $userInfo['sub'] ?? '';
        $email    = Sanitize::email($userInfo['email'] ?? '');
        $nome     = Sanitize::string($userInfo['name'] ?? '');

        if (!$googleId || !$email) {
            Response::error('OAUTH_ERROR', 'Dados do Google incompletos', 500);
        }

        // Busca ou cria usuário
        $users = Database::query(
            'SELECT u.*, s.id as seller_id, s.nome_loja, s.categoria, s.descricao_loja
             FROM users u
             LEFT JOIN sellers s ON u.id = s.user_id
             WHERE u.google_id = ? OR u.email = ?
             LIMIT 1',
            [$googleId, $email]
        );

        if (empty($users)) {
            // Cria novo usuário
            Database::execute(
                'INSERT INTO users (tipo, nome, email, google_id) VALUES (?, ?, ?, ?)',
                ['cliente', $nome ?: $email, $email, $googleId]
            );
            $users = Database::query('SELECT * FROM users WHERE email = ? LIMIT 1', [$email]);
        } else {
            // Atualiza google_id se necessário
            if (empty($users[0]['google_id'])) {
                Database::execute(
                    'UPDATE users SET google_id = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?',
                    [$googleId, $users[0]['id']]
                );
            }
        }

        $user   = $users[0];
        $secret = Config::require('JWT_SECRET');
        $role   = RBAC::resolveUserRole($user);
        $token  = Auth::generateToken(
            ['id' => $user['id'], 'email' => $user['email'], 'tipo' => $user['tipo'], 'role' => $role],
            $secret,
            7200
        );

        // Redireciona para o frontend com o token
        $frontendUrl = Config::get('FRONTEND_URL', '/');
        $redirect    = rtrim($frontendUrl, '/') . '?token=' . urlencode($token);
        header('Location: ' . $redirect, true, 302);
        exit;
    }

    private function httpPost(string $url, array $data): array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => http_build_query($data),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);
        $response = curl_exec($ch);
        curl_close($ch);
        return json_decode((string)$response, true) ?? [];
    }

    private function httpGet(string $url, string $accessToken): array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_HTTPHEADER     => ["Authorization: Bearer {$accessToken}"],
            CURLOPT_SSL_VERIFYPEER => true,
        ]);
        $response = curl_exec($ch);
        curl_close($ch);
        return json_decode((string)$response, true) ?? [];
    }
}
