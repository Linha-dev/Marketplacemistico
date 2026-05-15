<?php
class StatusHistoryHandler
{
    public function handle(array $ctx): void
    {
        if ($ctx['method'] !== 'GET') {
            Response::methodNotAllowed();
        }

        $query    = $ctx['query'];
        $page     = max(1, Sanitize::integer($query['page'] ?? 1) ?? 1);
        $limit    = min(100, max(1, Sanitize::integer($query['limit'] ?? 20) ?? 20));
        $offset   = ($page - 1) * $limit;
        $provider = Sanitize::string($query['provider'] ?? '');
        $status   = Sanitize::string($query['status'] ?? '');

        $where  = '';
        $params = [];

        if ($provider) {
            $where   .= ($where ? ' AND' : ' WHERE') . ' provider = ?';
            $params[] = $provider;
        }
        if ($status) {
            $where   .= ($where ? ' AND' : ' WHERE') . ' status = ?';
            $params[] = $status;
        }

        $count = Database::query("SELECT COUNT(*) as total FROM webhook_events{$where}", $params);
        $total = (int)($count[0]['total'] ?? 0);

        $events = Database::query(
            "SELECT id, provider, event_type, external_id, status, processed_at, created_at
             FROM webhook_events{$where}
             ORDER BY created_at DESC LIMIT ? OFFSET ?",
            array_merge($params, [$limit, $offset])
        );

        Response::success([
            'events'     => $events,
            'pagination' => [
                'page'       => $page,
                'limit'      => $limit,
                'total'      => $total,
                'totalPages' => (int)ceil($total / $limit),
            ],
        ]);
    }
}
