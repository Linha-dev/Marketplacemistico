<?php
/**
 * Google OAuth - Redireciona para o Google para autorização.
 */
class GoogleAuthHandler
{
    public function handle(array $ctx): void
    {
        if ($ctx['method'] !== 'GET') {
            Response::methodNotAllowed();
        }

        $clientId    = Config::require('GOOGLE_CLIENT_ID');
        $redirectUri = Config::require('GOOGLE_REDIRECT_URI');

        $params = http_build_query([
            'client_id'     => $clientId,
            'redirect_uri'  => $redirectUri,
            'response_type' => 'code',
            'scope'         => 'openid email profile',
            'access_type'   => 'offline',
            'prompt'        => 'consent',
        ]);

        $url = 'https://accounts.google.com/o/oauth2/v2/auth?' . $params;
        header('Location: ' . $url, true, 302);
        exit;
    }
}
