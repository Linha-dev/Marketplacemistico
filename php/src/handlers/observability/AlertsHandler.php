<?php
class AlertsHandler
{
    public function handle(array $ctx): void
    {
        if ($ctx['method'] !== 'GET') {
            Response::methodNotAllowed();
        }

        $alertsSecret   = Config::get('ALERTS_SECRET', '');
        $receivedSecret = $ctx['query']['secret'] ?? ($ctx['headers']['x-alerts-secret'] ?? '');
        if ($alertsSecret !== '' && $alertsSecret !== $receivedSecret) {
            Response::error('UNAUTHORIZED', 'Alerts secret inválido', 401);
        }

        // Contagem de webhooks com falha na janela de tempo
        $failedWindowMinutes    = (int)Config::get('ALERT_WEBHOOK_FAILED_WINDOW_MINUTES', '30');
        $failedThreshold        = (int)Config::get('ALERT_WEBHOOK_FAILED_THRESHOLD', '5');
        $stuckMinutes           = (int)Config::get('ALERT_WEBHOOK_STUCK_MINUTES', '15');
        $reconciliationStaleDays = (int)Config::get('ALERT_RECONCILIATION_STALE_DAYS', '0');

        $driver = Database::driver();
        $dateFunc = $driver === 'pgsql'
            ? "created_at > NOW() - INTERVAL '{$failedWindowMinutes} minutes'"
            : "created_at > DATE_SUB(NOW(), INTERVAL {$failedWindowMinutes} MINUTE)";

        $failedWebhooks = Database::query(
            "SELECT COUNT(*) as total FROM webhook_events WHERE status = 'failed' AND {$dateFunc}"
        );
        $failedCount = (int)($failedWebhooks[0]['total'] ?? 0);

        $stuckDateFunc = $driver === 'pgsql'
            ? "created_at < NOW() - INTERVAL '{$stuckMinutes} minutes'"
            : "created_at < DATE_SUB(NOW(), INTERVAL {$stuckMinutes} MINUTE)";

        $stuckWebhooks = Database::query(
            "SELECT COUNT(*) as total FROM webhook_events WHERE status = 'received' AND {$stuckDateFunc}"
        );
        $stuckCount = (int)($stuckWebhooks[0]['total'] ?? 0);

        // Verifica reconciliação recente
        $reconStaleDays = $reconciliationStaleDays > 0 ? $reconciliationStaleDays : 1;
        $reconFunc = $driver === 'pgsql'
            ? "processed_at > NOW() - INTERVAL '{$reconStaleDays} days'"
            : "processed_at > DATE_SUB(NOW(), INTERVAL {$reconStaleDays} DAY)";

        $recentReconciliation = Database::query(
            "SELECT COUNT(*) as total FROM webhook_events WHERE provider = 'reconciliation' AND {$reconFunc}"
        );
        $reconRecent = (int)($recentReconciliation[0]['total'] ?? 0);

        $alerts = [];

        if ($failedCount >= $failedThreshold) {
            $alerts[] = [
                'type'    => 'WEBHOOK_FAILED_RATE',
                'level'   => 'critical',
                'message' => "{$failedCount} webhooks com falha nos últimos {$failedWindowMinutes} minutos (threshold: {$failedThreshold})",
                'count'   => $failedCount,
            ];
        }

        if ($stuckCount > 0) {
            $alerts[] = [
                'type'    => 'WEBHOOK_STUCK',
                'level'   => 'warning',
                'message' => "{$stuckCount} webhooks stuck (sem processamento há mais de {$stuckMinutes} minutos)",
                'count'   => $stuckCount,
            ];
        }

        if ($reconRecent === 0 && $reconciliationStaleDays > 0) {
            $alerts[] = [
                'type'    => 'RECONCILIATION_STALE',
                'level'   => 'warning',
                'message' => "Última conciliação há mais de {$reconStaleDays} dia(s)",
            ];
        }

        Response::success([
            'ok'        => empty($alerts),
            'alerts'    => $alerts,
            'timestamp' => gmdate('c'),
        ]);
    }
}
