<?php
class MetricsHandler
{
    public function handle(array $ctx): void
    {
        if ($ctx['method'] !== 'GET') {
            Response::methodNotAllowed();
        }

        $metricsSecret  = Config::get('METRICS_SECRET', '');
        $receivedSecret = $ctx['query']['secret'] ?? ($ctx['headers']['x-metrics-secret'] ?? '');
        if ($metricsSecret !== '' && $metricsSecret !== $receivedSecret) {
            Response::error('UNAUTHORIZED', 'Metrics secret inválido', 401);
        }

        // Em PHP (shared hosting), métricas em memória não persistem entre requests.
        // Fornecemos métricas básicas derivadas do banco de dados.
        $orders = Database::query(
            "SELECT COUNT(*) as total, SUM(grand_total) as revenue FROM orders WHERE payment_status = 'approved'"
        );
        $pendingOrders = Database::query(
            "SELECT COUNT(*) as total FROM orders WHERE payment_status = 'pending'"
        );
        $webhookFailed = Database::query(
            "SELECT COUNT(*) as total FROM webhook_events WHERE status = 'failed'"
        );
        $webhookReceived = Database::query(
            "SELECT COUNT(*) as total FROM webhook_events WHERE status = 'received'"
        );
        $manualPending = Database::query(
            "SELECT COUNT(*) as total, COALESCE(SUM(amount), 0) as amount FROM manual_payouts WHERE status = 'pending'"
        );

        Response::success([
            'orders_approved_total'    => (int)($orders[0]['total'] ?? 0),
            'orders_revenue_total'     => (float)($orders[0]['revenue'] ?? 0),
            'orders_pending_total'     => (int)($pendingOrders[0]['total'] ?? 0),
            'webhook_failed_total'     => (int)($webhookFailed[0]['total'] ?? 0),
            'webhook_received_total'   => (int)($webhookReceived[0]['total'] ?? 0),
            'manual_payouts_pending'   => (int)($manualPending[0]['total'] ?? 0),
            'manual_payouts_amount'    => (float)($manualPending[0]['amount'] ?? 0),
            'note'                     => 'Métricas derivadas do banco de dados. Contadores em memória não são suportados em shared hosting PHP.',
        ]);
    }
}
