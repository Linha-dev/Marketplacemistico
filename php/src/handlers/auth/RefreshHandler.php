<?php
class RefreshHandler
{
    public function handle(array $ctx): void
    {
        if ($ctx['method'] !== 'POST') {
            Response::methodNotAllowed();
        }

        $secret = Config::require('JWT_SECRET');
        $token  = Auth::extractBearerToken($ctx['headers']);
        if ($token === null) {
            Response::unauthorized('Token de autenticação ausente');
        }

        $decoded = Auth::verifyToken($token, $secret);
        if ($decoded === null) {
            Response::error('INVALID_TOKEN', 'Token inválido', 401);
        }

        $role     = RBAC::resolveUserRole($decoded);
        $newToken = Auth::generateToken(
            ['id' => $decoded['id'], 'email' => $decoded['email'], 'tipo' => $decoded['tipo'], 'role' => $role],
            $secret,
            7200
        );

        Response::success(['token' => $newToken]);
    }
}
