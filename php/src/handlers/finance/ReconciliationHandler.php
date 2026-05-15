<?php
class ReconciliationHandler
{
    public function handle(array $ctx): void
    {
        if ($ctx['method'] !== 'POST') {
            Response::methodNotAllowed();
        }

        // Requer secret de operações financeiras
        $opsSecret      = Config::get('FINANCE_OPS_SECRET', '');
        $receivedSecret = $ctx['headers']['x-finance-ops-secret'] ?? $ctx['headers']['X-Finance-Ops-Secret'] ?? '';
        if ($opsSecret === '' || $opsSecret !== $receivedSecret) {
            Response::error('UNAUTHORIZED', 'Finance ops secret inválido', 401);
        }

        $date = Sanitize::string($ctx['body']['date'] ?? $ctx['query']['date'] ?? date('Y-m-d'));

        // Conciliação diária:
        // - Compara pagamentos aprovados com splits ready
        // - Marca manual_payouts como pagos se scheduled_for <= hoje
        $paidCount = Database::execute(
            'UPDATE manual_payouts SET status = ?, paid_at = CURRENT_TIMESTAMP
             WHERE status = ? AND scheduled_for <= ?',
            ['paid', 'pending', $date . ' 23:59:59']
        )->rowCount();

        // Resumo de pagamentos do dia
        $payments = Database::query(
            'SELECT COUNT(*) as total, SUM(amount) as total_amount
             FROM payments
             WHERE status = ? AND DATE(paid_at) = ?',
            ['approved', $date]
        );

        // Splits sem pagamento aprovado (inconsistências)
        $inconsistencies = Database::query(
            'SELECT ps.id, ps.payment_id, ps.seller_id, ps.status, ps.gross_amount
             FROM payment_splits ps
             JOIN payments p ON p.id = ps.payment_id
             WHERE DATE(p.created_at) = ? AND ps.status = ? AND p.status != ?',
            [$date, 'pending', 'approved']
        );

        Response::success([
            'date'            => $date,
            'manual_payouts_paid' => $paidCount,
            'payments_total'  => (int)($payments[0]['total'] ?? 0),
            'payments_amount' => (float)($payments[0]['total_amount'] ?? 0),
            'inconsistencies' => $inconsistencies,
        ]);
    }
}
