<?php
/**
 * EfiClient - Cliente PHP para a API EFI (Pix).
 * Equivalente de backend/services/payments/efi-client.js.
 */
class EfiClient
{
    private static ?string $tokenCache = null;
    private static int $tokenExpiresAt = 0;

    public static function isMock(): bool
    {
        return Config::bool('EFI_MOCK') || Config::get('APP_ENV') === 'test';
    }

    private static function baseUrl(): string
    {
        return Config::get('EFI_BASE_URL', 'https://pix-h.api.efipay.com.br');
    }

    private static function fetchToken(): string
    {
        $now = time();
        if (self::$tokenCache !== null && self::$tokenExpiresAt > $now + 10) {
            return self::$tokenCache;
        }

        $clientId     = Config::require('EFI_CLIENT_ID');
        $clientSecret = Config::require('EFI_CLIENT_SECRET');
        $tokenUrl     = Config::get('EFI_TOKEN_URL', self::baseUrl() . '/oauth/token');
        $basic        = base64_encode("{$clientId}:{$clientSecret}");

        $response = self::httpPost($tokenUrl, json_encode(['grant_type' => 'client_credentials']), [
            "Authorization: Basic {$basic}",
            'Content-Type: application/json',
        ]);

        if (!isset($response['access_token'])) {
            throw new RuntimeException('Falha ao autenticar com EFI: ' . json_encode($response));
        }

        self::$tokenCache    = $response['access_token'];
        self::$tokenExpiresAt = $now + (int)($response['expires_in'] ?? 300);

        return self::$tokenCache;
    }

    public static function createPixCharge(array $payload): array
    {
        if (self::isMock()) {
            return [
                'provider_charge_id' => 'efi_mock_' . time(),
                'status'             => 'pending',
                'payment_method'     => 'pix',
                'pix_qr_code'        => '00020126580014BR.GOV.BCB.PIX0136mock-pix-key',
                'pix_copy_paste'     => '00020126580014BR.GOV.BCB.PIX0136mock-pix-key',
                'raw'                => ['mock' => true, 'payload' => $payload],
            ];
        }

        $token     = self::fetchToken();
        $chargeUrl = Config::get('EFI_PIX_CHARGE_URL', self::baseUrl() . '/v2/cob');

        return self::httpPost($chargeUrl, json_encode($payload), [
            "Authorization: Bearer {$token}",
            'Content-Type: application/json',
        ]);
    }

    public static function createPixRefund(array $options): array
    {
        $providerChargeId = $options['providerChargeId'];
        $amount           = $options['amount'];
        $refundReference  = $options['refundReference'] ?? 'refund_' . time();

        if (self::isMock()) {
            return [
                'provider_refund_id'  => $refundReference,
                'provider_charge_id'  => $providerChargeId,
                'status'              => 'processed',
                'amount'              => $amount,
                'raw'                 => ['mock' => true],
            ];
        }

        $token     = self::fetchToken();
        $refundUrl = Config::get('EFI_PIX_REFUND_URL', self::baseUrl() . "/v2/pix/{$providerChargeId}/devolucao/{$refundReference}");

        return self::httpPut($refundUrl, json_encode(['valor' => number_format((float)$amount, 2, '.', '')]), [
            "Authorization: Bearer {$token}",
            'Content-Type: application/json',
        ]);
    }

    // -----------------------------------------------------------------------
    // HTTP helpers
    // -----------------------------------------------------------------------

    private static function httpPost(string $url, string $body, array $headers): array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $body,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $data = json_decode((string)$response, true) ?? [];
        if ($httpCode >= 400) {
            throw new RuntimeException("Erro HTTP {$httpCode} ao chamar EFI: " . json_encode($data));
        }
        return $data;
    }

    private static function httpPut(string $url, string $body, array $headers): array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST  => 'PUT',
            CURLOPT_POSTFIELDS     => $body,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $data = json_decode((string)$response, true) ?? [];
        if ($httpCode >= 400) {
            throw new RuntimeException("Erro HTTP {$httpCode} ao chamar EFI refund: " . json_encode($data));
        }
        return $data;
    }
}
