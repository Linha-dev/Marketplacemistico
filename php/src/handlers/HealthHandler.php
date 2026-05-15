<?php
class HealthHandler
{
    public function handle(array $ctx): void
    {
        if ($ctx['method'] !== 'GET') {
            Response::methodNotAllowed();
        }

        $dbStatus = 'disconnected';
        try {
            Database::query('SELECT 1');
            $dbStatus = 'connected';
        } catch (Throwable $e) {
            error_log('[health] DB check failed: ' . $e->getMessage());
        }

        Response::success([
            'status'    => 'ok',
            'database'  => $dbStatus,
            'timestamp' => gmdate('c'),
            'version'   => '1.0.0',
        ]);
    }
}
