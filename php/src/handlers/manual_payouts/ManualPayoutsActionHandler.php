<?php
class ManualPayoutsActionHandler
{
    public function handle(array $ctx): void
    {
        if ($ctx['method'] !== 'POST') {
            Response::methodNotAllowed();
        }

        Auth::requireInternalRole($ctx['headers']);

        $opsSecret      = Config::get('FINANCE_OPS_SECRET', '');
        $receivedSecret = $ctx['headers']['x-finance-ops-secret'] ?? $ctx['headers']['X-Finance-Ops-Secret'] ?? '';
        if ($opsSecret !== '' && $opsSecret !== $receivedSecret) {
            Response::error('UNAUTHORIZED', 'Finance ops secret inválido', 401);
        }

        $id     = Sanitize::integer($ctx['params']['id'] ?? null);
        $action = Sanitize::string($ctx['body']['action'] ?? '');
        $externalReference = Sanitize::string($ctx['body']['external_reference'] ?? '');

        if (!$id) {
            Response::error('VALIDATION_ERROR', 'ID inválido');
        }

        if (!in_array($action, ['mark_paid', 'cancel'], true)) {
            Response::error('VALIDATION_ERROR', 'action deve ser mark_paid ou cancel');
        }

        $payouts = Database::query('SELECT * FROM manual_payouts WHERE id = ? LIMIT 1', [$id]);
        if (empty($payouts)) {
            Response::notFound('Repasse não encontrado');
        }

        $payout = $payouts[0];
        if ($action === 'mark_paid') {
            if ($payout['status'] !== 'pending') {
                Response::error('INVALID_TRANSITION', 'Apenas repasses pendentes podem ser marcados como pagos');
            }
            Database::execute(
                'UPDATE manual_payouts SET status = ?, paid_at = CURRENT_TIMESTAMP, external_reference = ? WHERE id = ?',
                ['paid', $externalReference ?: null, $id]
            );
        } elseif ($action === 'cancel') {
            if ($payout['status'] === 'paid') {
                Response::error('INVALID_TRANSITION', 'Repasse já pago não pode ser cancelado');
            }
            Database::execute('UPDATE manual_payouts SET status = ? WHERE id = ?', ['cancelled', $id]);
        }

        $updated = Database::query('SELECT * FROM manual_payouts WHERE id = ?', [$id]);
        Response::success(['payout' => $updated[0]]);
    }
}
