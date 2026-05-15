<?php
/**
 * Auth - Geração e verificação de JWT (HS256, implementação pura PHP).
 * Não requer nenhuma biblioteca externa.
 */
class Auth
{
    // -----------------------------------------------------------------------
    // JWT helpers
    // -----------------------------------------------------------------------

    private static function b64url_encode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private static function b64url_decode(string $data): string
    {
        $pad  = 4 - (strlen($data) % 4);
        $data = $data . ($pad < 4 ? str_repeat('=', $pad) : '');
        return base64_decode(strtr($data, '-_', '+/'));
    }

    /**
     * Gera um JWT HS256.
     *
     * @param array  $payload    Dados do usuário (sem iat/exp — adicionados aqui)
     * @param string $secret     JWT_SECRET
     * @param int    $expiresIn  Segundos até expirar (default: 7200 = 2h)
     */
    public static function generateToken(array $payload, string $secret, int $expiresIn = 7200): string
    {
        $header  = self::b64url_encode(json_encode(['alg' => 'HS256', 'typ' => 'JWT']));
        $payload['iat'] = time();
        $payload['exp'] = time() + $expiresIn;
        $encodedPayload  = self::b64url_encode(json_encode($payload));
        $sig = self::b64url_encode(hash_hmac('sha256', "{$header}.{$encodedPayload}", $secret, true));
        return "{$header}.{$encodedPayload}.{$sig}";
    }

    /**
     * Verifica e decodifica um JWT.
     * Retorna o payload como array associativo, ou null se inválido/expirado.
     */
    public static function verifyToken(string $token, string $secret): ?array
    {
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            return null;
        }
        [$headerB64, $payloadB64, $sigB64] = $parts;

        $expectedSig = self::b64url_encode(
            hash_hmac('sha256', "{$headerB64}.{$payloadB64}", $secret, true)
        );

        if (!hash_equals($expectedSig, $sigB64)) {
            return null;
        }

        $payload = json_decode(self::b64url_decode($payloadB64), true);
        if (!is_array($payload)) {
            return null;
        }

        if (isset($payload['exp']) && $payload['exp'] < time()) {
            return null;
        }

        return $payload;
    }

    // -----------------------------------------------------------------------
    // Extrai token do cabeçalho Authorization: Bearer <token>
    // -----------------------------------------------------------------------

    public static function extractBearerToken(array $headers): ?string
    {
        $auth = $headers['Authorization'] ?? $headers['authorization'] ?? '';
        if (str_starts_with($auth, 'Bearer ')) {
            return substr($auth, 7);
        }
        return null;
    }

    // -----------------------------------------------------------------------
    // Retorna o payload do token da requisição atual ou encerra com 401
    // -----------------------------------------------------------------------

    public static function requireAuth(array $headers): array
    {
        $secret = Config::require('JWT_SECRET');
        $token  = self::extractBearerToken($headers);
        if ($token === null) {
            Response::unauthorized('Token de autenticação ausente');
        }
        $payload = self::verifyToken($token, $secret);
        if ($payload === null) {
            Response::unauthorized('Token de autenticação inválido ou expirado');
        }
        // Resolve role (pode não estar no token; RBAC recalcula)
        $payload['role'] = RBAC::resolveUserRole($payload);
        return $payload;
    }

    public static function requireVendedor(array $headers): array
    {
        $user = self::requireAuth($headers);
        if (($user['tipo'] ?? '') !== 'vendedor') {
            Response::forbidden('Acesso restrito a vendedores');
        }
        return $user;
    }

    public static function requireInternalRole(array $headers, array $allowedRoles = ['operator', 'admin']): array
    {
        $user = self::requireAuth($headers);
        if (!RBAC::hasRole($user['role'] ?? 'user', $allowedRoles)) {
            Response::forbidden('Acesso restrito a operação interna');
        }
        return $user;
    }
}
