<?php
class ManualPayoutsHandler
{
    public function handle(array $ctx): void
    {
        if ($ctx['method'] !== 'GET') {
            Response::methodNotAllowed();
        }

        // Exige role interno (operator/admin) + finance ops secret
        $user = Auth::requireInternalRole($ctx['headers']);

        $opsSecret      = Config::get('FINANCE_OPS_SECRET', '');
        $receivedSecret = $ctx['headers']['x-finance-ops-secret'] ?? $ctx['headers']['X-Finance-Ops-Secret'] ?? '';
        if ($opsSecret !== '' && $opsSecret !== $receivedSecret) {
            Response::error('UNAUTHORIZED', 'Finance ops secret inválido', 401);
        }

        $query    = $ctx['query'];
        $page     = max(1, Sanitize::integer($query['page'] ?? 1) ?? 1);
        $limit    = min(100, max(1, Sanitize::integer($query['limit'] ?? 20) ?? 20));
        $offset   = ($page - 1) * $limit;
        $status   = Sanitize::string($query['status'] ?? '');
        $sellerId = Sanitize::integer($query['seller_id'] ?? null);

        $where    = '';
        $params   = [];

        if ($status) {
            $where   .= ($where ? ' AND' : ' WHERE') . ' mp.status = ?';
            $params[] = $status;
        }
        if ($sellerId) {
            $where   .= ($where ? ' AND' : ' WHERE') . ' mp.seller_id = ?';
            $params[] = $sellerId;
        }

        $count  = Database::query("SELECT COUNT(*) as total FROM manual_payouts mp{$where}", $params);
        $total  = (int)($count[0]['total'] ?? 0);

        $payouts = Database::query(
            "SELECT mp.*, s.nome_loja, u.nome as seller_nome, u.email as seller_email
             FROM manual_payouts mp
             JOIN sellers s ON s.id = mp.seller_id
             JOIN users u ON u.id = s.user_id
             {$where}
             ORDER BY mp.created_at DESC LIMIT ? OFFSET ?",
            array_merge($params, [$limit, $offset])
        );

        Response::success([
            'payouts'    => $payouts,
            'pagination' => [
                'page'       => $page,
                'limit'      => $limit,
                'total'      => $total,
                'totalPages' => (int)ceil($total / $limit),
            ],
        ]);
    }
}
