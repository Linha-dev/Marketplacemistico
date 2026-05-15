<?php
/**
 * MelhorEnvioClient - Cliente PHP para a API Melhor Envio.
 */
class MelhorEnvioClient
{
    public static function isMock(): bool
    {
        return Config::bool('MELHOR_ENVIO_MOCK') || Config::get('APP_ENV') === 'test';
    }

    private static function baseUrl(): string
    {
        return Config::get('MELHOR_ENVIO_BASE_URL', 'https://sandbox.melhorenvio.com.br');
    }

    public static function quoteShipment(array $payload): array
    {
        if (self::isMock()) {
            return [
                ['id' => 'mock-pac',   'name' => 'PAC',   'company' => ['name' => 'Correios'], 'price' => '22.90', 'delivery_time' => 8,  'custom_price' => null],
                ['id' => 'mock-sedex', 'name' => 'SEDEX', 'company' => ['name' => 'Correios'], 'price' => '39.50', 'delivery_time' => 3,  'custom_price' => null],
            ];
        }

        $token    = Config::require('MELHOR_ENVIO_ACCESS_TOKEN');
        $quoteUrl = Config::get(
            'MELHOR_ENVIO_QUOTE_URL',
            self::baseUrl() . '/api/v2/me/shipment/calculate'
        );

        $ch = curl_init($quoteUrl);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($payload),
            CURLOPT_HTTPHEADER     => [
                "Authorization: Bearer {$token}",
                'Accept: application/json',
                'Content-Type: application/json',
                'User-Agent: MarketplaceMistico/1.0',
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $data = json_decode((string)$response, true) ?? [];
        if ($httpCode >= 400) {
            throw new RuntimeException("Erro {$httpCode} ao cotar frete no Melhor Envio: " . json_encode($data));
        }

        return $data;
    }

    /**
     * Mapeia a resposta do Melhor Envio para o formato interno.
     */
    public static function mapQuoteResponse(array $rawOptions): array
    {
        $result = [];
        foreach ($rawOptions as $option) {
            if (isset($option['error'])) {
                continue;
            }
            $result[] = [
                'serviceId'    => (string)($option['id'] ?? ''),
                'serviceName'  => (string)($option['name'] ?? ''),
                'carrierName'  => (string)($option['company']['name'] ?? ''),
                'price'        => (float)($option['price'] ?? 0),
                'customPrice'  => isset($option['custom_price']) ? (float)$option['custom_price'] : null,
                'deliveryTime' => (int)($option['delivery_time'] ?? 0),
                'raw'          => $option,
            ];
        }
        return $result;
    }

    /**
     * Constrói o payload para cotação de frete a partir do perfil do vendedor e itens.
     */
    public static function buildQuotePayload(array $sellerOrigin, string $destinationPostalCode, array $packageInfo): array
    {
        return [
            'from' => [
                'postal_code' => preg_replace('/\D/', '', $sellerOrigin['from_postal_code'] ?? ''),
            ],
            'to' => [
                'postal_code' => preg_replace('/\D/', '', $destinationPostalCode),
            ],
            'package' => [
                'height'   => (float)($packageInfo['height_cm'] ?? 5),
                'width'    => (float)($packageInfo['width_cm'] ?? 5),
                'length'   => (float)($packageInfo['length_cm'] ?? 5),
                'weight'   => (float)($packageInfo['weight_kg'] ?? 0.1),
            ],
            'options' => [
                'insurance_value' => (float)($packageInfo['insurance_value'] ?? 0),
                'receipt'         => false,
                'own_hand'        => false,
            ],
        ];
    }
}
