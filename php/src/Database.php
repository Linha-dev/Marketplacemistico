<?php
/**
 * Database - PDO wrapper para MySQL (padrão Hostinger) ou PostgreSQL (VPS).
 *
 * Compatível com:
 *   MySQL/MariaDB  →  DB_DRIVER=mysql  (padrão)
 *   PostgreSQL     →  DB_DRIVER=pgsql
 *
 * Variáveis de ambiente aceitas:
 *   DATABASE_URL  →  mysql://user:pass@host:3306/dbname  (formato URL)
 *   Ou individualmente:
 *     DB_DRIVER, DB_HOST, DB_PORT, DB_NAME, DB_USER, DB_PASS
 */
class Database
{
    private static ?PDO $instance = null;

    public static function getConnection(): PDO
    {
        if (self::$instance === null) {
            self::$instance = self::connect();
        }
        return self::$instance;
    }

    private static function connect(): PDO
    {
        $url = Config::get('DATABASE_URL');

        if ($url !== '') {
            // Suporte ao formato mysql://user:pass@host:3306/dbname
            $parsed = parse_url($url);
            $driver = isset($parsed['scheme']) ? $parsed['scheme'] : 'mysql';
            // Normaliza 'postgres' para 'pgsql'
            if ($driver === 'postgres') {
                $driver = 'pgsql';
            }
            $host   = $parsed['host'] ?? 'localhost';
            $port   = $parsed['port'] ?? ($driver === 'pgsql' ? 5432 : 3306);
            $dbname = ltrim($parsed['path'] ?? '', '/');
            $user   = urldecode($parsed['user'] ?? '');
            $pass   = urldecode($parsed['pass'] ?? '');
        } else {
            $driver = Config::get('DB_DRIVER', 'mysql');
            $host   = Config::get('DB_HOST', 'localhost');
            $port   = Config::get('DB_PORT', $driver === 'pgsql' ? '5432' : '3306');
            $dbname = Config::require('DB_NAME');
            $user   = Config::require('DB_USER');
            $pass   = Config::get('DB_PASS', '');
        }

        if ($driver === 'pgsql') {
            $dsn = "pgsql:host={$host};port={$port};dbname={$dbname}";
        } else {
            $dsn = "mysql:host={$host};port={$port};dbname={$dbname};charset=utf8mb4";
        }

        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];

        if ($driver !== 'pgsql') {
            // Configurações extras para MySQL
            $options[PDO::MYSQL_ATTR_INIT_COMMAND] = "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci";
        }

        return new PDO($dsn, $user, $pass, $options);
    }

    /**
     * Executa uma query e retorna todas as linhas.
     */
    public static function query(string $sql, array $params = []): array
    {
        $pdo  = self::getConnection();
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * Executa uma query e retorna o PDOStatement (útil para rowCount etc.).
     */
    public static function execute(string $sql, array $params = []): PDOStatement
    {
        $pdo  = self::getConnection();
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    /**
     * Inicia uma transação e executa o callback.
     * Faz COMMIT em caso de sucesso, ROLLBACK em caso de exceção.
     */
    public static function transaction(callable $callback): mixed
    {
        $pdo = self::getConnection();
        $pdo->beginTransaction();
        try {
            $result = $callback($pdo);
            $pdo->commit();
            return $result;
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    /**
     * Retorna o driver em uso: 'mysql' ou 'pgsql'
     */
    public static function driver(): string
    {
        return self::getConnection()->getAttribute(PDO::ATTR_DRIVER_NAME);
    }

    /**
     * Gera placeholders dinâmicos para uso em IN (...) com múltiplos valores.
     * Ex: Database::placeholders([1,2,3]) → '?,?,?'
     */
    public static function placeholders(array $values): string
    {
        return implode(',', array_fill(0, count($values), '?'));
    }

    /**
     * Insere uma linha e retorna o registro inserido via SELECT.
     * No MySQL não há RETURNING; usamos lastInsertId() + SELECT.
     */
    public static function insertAndFetch(string $table, string $sql, array $params): array
    {
        $pdo  = self::getConnection();
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        if (self::driver() === 'pgsql') {
            // No PostgreSQL, RETURNING já está no SQL — retorna diretamente
            $row = $stmt->fetch();
            return $row !== false ? $row : [];
        }

        $id = $pdo->lastInsertId();
        if (!$id) {
            return [];
        }
        $rows = self::query("SELECT * FROM {$table} WHERE id = ?", [(int)$id]);
        return $rows[0] ?? [];
    }
}
