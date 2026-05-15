<?php
/**
 * Config - Carrega variáveis de ambiente do arquivo .env ou do ambiente do servidor.
 * Compatível com Hostinger shared hosting e VPS.
 */
class Config
{
    private static bool $loaded = false;

    public static function load(): void
    {
        if (self::$loaded) {
            return;
        }

        // Tenta carregar .env na raiz do projeto (um nível acima de php/)
        $envFile = dirname(__DIR__) . '/.env';
        if (file_exists($envFile)) {
            $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($lines as $line) {
                $line = trim($line);
                if ($line === '' || str_starts_with($line, '#')) {
                    continue;
                }
                if (strpos($line, '=') === false) {
                    continue;
                }
                [$key, $value] = explode('=', $line, 2);
                $key   = trim($key);
                $value = trim($value, " \t\n\r\0\x0B\"'");
                if (!isset($_ENV[$key]) && !getenv($key)) {
                    putenv("$key=$value");
                    $_ENV[$key]    = $value;
                    $_SERVER[$key] = $value;
                }
            }
        }

        self::$loaded = true;
    }

    public static function get(string $key, string $default = ''): string
    {
        self::load();
        $val = getenv($key);
        return ($val !== false && $val !== '') ? $val : $default;
    }

    public static function require(string $key): string
    {
        $val = self::get($key);
        if ($val === '') {
            http_response_code(503);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => ['code' => 'MISSING_ENV', 'message' => "Variavel de ambiente obrigatoria ausente: $key"]]);
            exit;
        }
        return $val;
    }

    public static function bool(string $key, bool $default = false): bool
    {
        $val = strtolower(self::get($key));
        if ($val === '') {
            return $default;
        }
        return in_array($val, ['true', '1', 'yes'], true);
    }
}
