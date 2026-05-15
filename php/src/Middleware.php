<?php
/**
 * Middleware - CORS, cabeçalhos de segurança e correlação.
 */
class Middleware
{
    /**
     * Aplica todos os cabeçalhos de segurança e CORS.
     * Retorna o correlationId gerado para a requisição.
     */
    public static function apply(array $headers): string
    {
        $correlationId = $headers['X-Correlation-Id']
            ?? $headers['x-correlation-id']
            ?? self::generateUuid();

        // Cabeçalhos de segurança
        header("X-Correlation-Id: {$correlationId}");
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: DENY');
        header('X-XSS-Protection: 1; mode=block');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
        header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline'; img-src 'self' https: data:");
        header('X-Permitted-Cross-Domain-Policies: none');

        // CORS
        $allowedOriginEnv = Config::get('ALLOWED_ORIGIN', '');
        if ($allowedOriginEnv !== '') {
            $allowedOrigins = array_map('trim', explode(',', $allowedOriginEnv));
            $requestOrigin  = $headers['Origin'] ?? $headers['origin'] ?? '';
            if ($requestOrigin !== '' && in_array($requestOrigin, $allowedOrigins, true)) {
                header("Access-Control-Allow-Origin: {$requestOrigin}");
                header('Vary: Origin');
                header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, PATCH, OPTIONS');
                header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Correlation-Id');
            }
        }

        return $correlationId;
    }

    /**
     * Responde ao preflight OPTIONS e encerra.
     */
    public static function handlePreflight(string $method): void
    {
        if (strtoupper($method) === 'OPTIONS') {
            http_response_code(200);
            exit;
        }
    }

    /**
     * Lê e decodifica o body JSON da requisição.
     * Retorna array vazio se o body estiver vazio ou não for JSON.
     */
    public static function readJsonBody(): array
    {
        $raw = file_get_contents('php://input');
        if ($raw === '' || $raw === false) {
            return [];
        }
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : [];
    }

    /**
     * Retorna todos os cabeçalhos da requisição como array associativo.
     */
    public static function getRequestHeaders(): array
    {
        if (function_exists('getallheaders')) {
            $headers = getallheaders();
            return is_array($headers) ? $headers : [];
        }
        // Fallback para ambientes sem getallheaders()
        $headers = [];
        foreach ($_SERVER as $key => $value) {
            if (str_starts_with($key, 'HTTP_')) {
                $name            = str_replace('_', '-', ucwords(strtolower(substr($key, 5)), '_'));
                $headers[$name]  = $value;
            } elseif (in_array($key, ['CONTENT_TYPE', 'CONTENT_LENGTH'], true)) {
                $name            = str_replace('_', '-', ucwords(strtolower($key), '_'));
                $headers[$name]  = $value;
            }
        }
        return $headers;
    }

    // -----------------------------------------------------------------------
    // UUID v4 (sem extensão ext-uuid)
    // -----------------------------------------------------------------------

    private static function generateUuid(): string
    {
        $data    = random_bytes(16);
        $data[6] = chr((ord($data[6]) & 0x0F) | 0x40);
        $data[8] = chr((ord($data[8]) & 0x3F) | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}
