<?php
/**
 * RateLimit - Limitador de taxa baseado em banco de dados.
 * Compatível com Hostinger shared hosting (sem Redis/APCu obrigatório).
 *
 * Requer a tabela `rate_limits` (criada automaticamente na primeira vez).
 */
class RateLimit
{
    private static bool $tableChecked = false;

    /**
     * Verifica o rate limit para um IP + path.
     * Encerra com 429 caso o limite seja excedido.
     *
     * @param int    $windowMs  Janela em milissegundos
     * @param int    $max       Máximo de requests na janela
     * @param string $message   Mensagem de erro
     */
    public static function check(int $windowMs, int $max, string $message = 'Muitas tentativas. Aguarde alguns minutos.'): void
    {
        self::ensureTable();

        $ip   = self::getClientIp();
        $path = strtok($_SERVER['REQUEST_URI'] ?? '/', '?') ?: '/';
        $key  = substr($ip . ':' . $path, 0, 255);
        $now  = (int)(microtime(true) * 1000); // ms
        $windowStart = $now - $windowMs;

        try {
            $db = Database::getConnection();

            // Limpa janelas antigas
            $db->prepare('DELETE FROM rate_limits WHERE window_start_ms < ?')
               ->execute([$windowStart]);

            // Verifica ou cria o registro
            $rows = Database::query(
                'SELECT count_val, window_start_ms FROM rate_limits WHERE rate_key = ? LIMIT 1',
                [$key]
            );

            if (empty($rows) || ($rows[0]['window_start_ms'] < $windowStart)) {
                // Nova janela
                Database::execute(
                    'REPLACE INTO rate_limits (rate_key, count_val, window_start_ms) VALUES (?, 1, ?)',
                    [$key, $now]
                );
                $count = 1;
            } else {
                $count = (int)$rows[0]['count_val'] + 1;
                Database::execute(
                    'UPDATE rate_limits SET count_val = ? WHERE rate_key = ?',
                    [$count, $key]
                );
            }

            if ($count > $max) {
                $retryAfterMs = ($rows[0]['window_start_ms'] ?? $now) + $windowMs - $now;
                $retryAfterSec = (int)ceil(max(0, $retryAfterMs) / 1000);
                header("Retry-After: {$retryAfterSec}");
                Response::error('RATE_LIMIT_EXCEEDED', $message, 429);
            }
        } catch (Throwable) {
            // Se o rate limiter falhar, deixa a requisição passar (fail open)
        }
    }

    private static function ensureTable(): void
    {
        if (self::$tableChecked) {
            return;
        }
        self::$tableChecked = true;

        try {
            $driver = Database::driver();
            if ($driver === 'pgsql') {
                Database::execute(
                    'CREATE TABLE IF NOT EXISTS rate_limits (
                        rate_key VARCHAR(255) PRIMARY KEY,
                        count_val INTEGER NOT NULL DEFAULT 1,
                        window_start_ms BIGINT NOT NULL
                    )'
                );
            } else {
                Database::execute(
                    'CREATE TABLE IF NOT EXISTS rate_limits (
                        rate_key VARCHAR(255) NOT NULL PRIMARY KEY,
                        count_val INT NOT NULL DEFAULT 1,
                        window_start_ms BIGINT NOT NULL
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
                );
            }
        } catch (Throwable) {
            // Ignora se a tabela já existir ou se não houver permissão
        }
    }

    private static function getClientIp(): string
    {
        $forwarded = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '';
        if ($forwarded !== '') {
            return trim(explode(',', $forwarded)[0]);
        }
        return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    }
}
