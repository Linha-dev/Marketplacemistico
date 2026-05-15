<?php
/**
 * API Entry Point - Marketplace Místico (PHP)
 * Compatível com Hostinger shared hosting e VPS.
 */

declare(strict_types=1);
error_reporting(E_ALL);
// Em produção, desabilite a exibição de erros (ative apenas log):
ini_set('display_errors', '0');
ini_set('log_errors', '1');

// Autoload de classes (sem Composer)
spl_autoload_register(function (string $class): void {
    $map = [
        'Config'     => __DIR__ . '/../src/Config.php',
        'Database'   => __DIR__ . '/../src/Database.php',
        'Response'   => __DIR__ . '/../src/Response.php',
        'Auth'       => __DIR__ . '/../src/Auth.php',
        'Middleware' => __DIR__ . '/../src/Middleware.php',
        'RateLimit'  => __DIR__ . '/../src/RateLimit.php',
        'Sanitize'   => __DIR__ . '/../src/Sanitize.php',
        'RBAC'       => __DIR__ . '/../src/RBAC.php',
        'Router'     => __DIR__ . '/../src/Router.php',

        // Handlers - Auth
        'HealthHandler'              => __DIR__ . '/../src/handlers/HealthHandler.php',
        'LoginHandler'               => __DIR__ . '/../src/handlers/auth/LoginHandler.php',
        'RegisterHandler'            => __DIR__ . '/../src/handlers/auth/RegisterHandler.php',
        'MeHandler'                  => __DIR__ . '/../src/handlers/auth/MeHandler.php',
        'RefreshHandler'             => __DIR__ . '/../src/handlers/auth/RefreshHandler.php',
        'GoogleAuthHandler'          => __DIR__ . '/../src/handlers/auth/GoogleAuthHandler.php',
        'GoogleCallbackHandler'      => __DIR__ . '/../src/handlers/auth/GoogleCallbackHandler.php',

        // Handlers - Products
        'ProductsIndexHandler'       => __DIR__ . '/../src/handlers/products/ProductsIndexHandler.php',
        'ProductsByIdHandler'        => __DIR__ . '/../src/handlers/products/ProductsByIdHandler.php',
        'ProductPublishHandler'      => __DIR__ . '/../src/handlers/products/ProductPublishHandler.php',

        // Handlers - Orders
        'OrdersIndexHandler'         => __DIR__ . '/../src/handlers/orders/OrdersIndexHandler.php',
        'OrdersByIdHandler'          => __DIR__ . '/../src/handlers/orders/OrdersByIdHandler.php',
        'OrderStatusHandler'         => __DIR__ . '/../src/handlers/orders/OrderStatusHandler.php',
        'OrderPostSaleHandler'       => __DIR__ . '/../src/handlers/orders/OrderPostSaleHandler.php',

        // Handlers - Payments
        'CreatePaymentHandler'       => __DIR__ . '/../src/handlers/payments/CreatePaymentHandler.php',
        'RefundPaymentHandler'       => __DIR__ . '/../src/handlers/payments/RefundPaymentHandler.php',

        // Handlers - Users
        'UserProfileHandler'         => __DIR__ . '/../src/handlers/users/UserProfileHandler.php',
        'UpgradeToVendorHandler'     => __DIR__ . '/../src/handlers/users/UpgradeToVendorHandler.php',
        'AddressesHandler'           => __DIR__ . '/../src/handlers/users/AddressesHandler.php',
        'AddressByIdHandler'         => __DIR__ . '/../src/handlers/users/AddressByIdHandler.php',

        // Handlers - Sellers
        'SellersMeHandler'           => __DIR__ . '/../src/handlers/sellers/SellersMeHandler.php',
        'SellersByIdHandler'         => __DIR__ . '/../src/handlers/sellers/SellersByIdHandler.php',
        'SellersMeOrdersHandler'     => __DIR__ . '/../src/handlers/sellers/SellersMeOrdersHandler.php',
        'SellersMeProductsHandler'   => __DIR__ . '/../src/handlers/sellers/SellersMeProductsHandler.php',

        // Handlers - Shipping
        'ShippingQuoteHandler'       => __DIR__ . '/../src/handlers/shipping/ShippingQuoteHandler.php',

        // Handlers - Webhooks
        'EfiWebhookHandler'          => __DIR__ . '/../src/handlers/webhooks/EfiWebhookHandler.php',
        'EfiRetryHandler'            => __DIR__ . '/../src/handlers/webhooks/EfiRetryHandler.php',
        'EfiReprocessHandler'        => __DIR__ . '/../src/handlers/webhooks/EfiReprocessHandler.php',
        'MelhorEnvioWebhookHandler'  => __DIR__ . '/../src/handlers/webhooks/MelhorEnvioWebhookHandler.php',

        // Handlers - Finance
        'LedgerHandler'              => __DIR__ . '/../src/handlers/finance/LedgerHandler.php',
        'ReconciliationHandler'      => __DIR__ . '/../src/handlers/finance/ReconciliationHandler.php',

        // Handlers - Manual Payouts
        'ManualPayoutsHandler'       => __DIR__ . '/../src/handlers/manual_payouts/ManualPayoutsHandler.php',
        'ManualPayoutsActionHandler' => __DIR__ . '/../src/handlers/manual_payouts/ManualPayoutsActionHandler.php',

        // Handlers - Observability
        'AlertsHandler'              => __DIR__ . '/../src/handlers/observability/AlertsHandler.php',
        'MetricsHandler'             => __DIR__ . '/../src/handlers/observability/MetricsHandler.php',
        'StatusHistoryHandler'       => __DIR__ . '/../src/handlers/observability/StatusHistoryHandler.php',

        // Services
        'EfiClient'                  => __DIR__ . '/../src/services/EfiClient.php',
        'MelhorEnvioClient'          => __DIR__ . '/../src/services/MelhorEnvioClient.php',
    ];

    if (isset($map[$class])) {
        require_once $map[$class];
    }
});

// Carrega configurações
Config::load();

// Cabeçalhos de segurança + CORS
$headers       = Middleware::getRequestHeaders();
$correlationId = Middleware::apply($headers);

// Responde ao preflight OPTIONS
Middleware::handlePreflight($_SERVER['REQUEST_METHOD'] ?? 'GET');

// Lê body JSON
$body   = Middleware::readJsonBody();
$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

// Remove prefix /api do path para o roteador (compatibilidade com .htaccess)
$requestUri  = $_SERVER['REQUEST_URI'] ?? '/';
$path        = strtok($requestUri, '?') ?: '/';
// Normaliza: /api/... ou apenas /...
if (!str_starts_with($path, '/api')) {
    $path = '/api' . $path;
}

// Contexto compartilhado entre todos os handlers
$context = [
    'method'        => $method,
    'headers'       => $headers,
    'body'          => $body,
    'query'         => $_GET,
    'correlationId' => $correlationId,
    'params'        => [],
];

// -----------------------------------------------------------------------
// Registro de rotas
// -----------------------------------------------------------------------
$router = new Router();

$router->add('/api/health',                                'HealthHandler');

// Auth
$router->add('/api/auth/google',                           'GoogleAuthHandler');
$router->add('/api/auth/login',                            'LoginHandler');
$router->add('/api/auth/me',                               'MeHandler');
$router->add('/api/auth/refresh',                          'RefreshHandler');
$router->add('/api/auth/register',                         'RegisterHandler');
$router->add('/api/auth/callback/google',                  'GoogleCallbackHandler');

// Orders (mais específicas antes das genéricas)
$router->add('/api/orders/:id/post-sale',                  'OrderPostSaleHandler');
$router->add('/api/orders/:id/status',                     'OrderStatusHandler');
$router->add('/api/orders/:id',                            'OrdersByIdHandler');
$router->add('/api/orders',                                'OrdersIndexHandler');

// Payments
$router->add('/api/payments/create',                       'CreatePaymentHandler');
$router->add('/api/payments/refund',                       'RefundPaymentHandler');

// Observability
$router->add('/api/observability/alerts',                  'AlertsHandler');
$router->add('/api/observability/metrics',                 'MetricsHandler');
$router->add('/api/status/history',                        'StatusHistoryHandler');

// Manual Payouts
$router->add('/api/manual-payouts/:id/action',             'ManualPayoutsActionHandler');
$router->add('/api/manual-payouts',                        'ManualPayoutsHandler');

// Finance
$router->add('/api/finance/ledger/:orderId',               'LedgerHandler');
$router->add('/api/finance/reconciliation/daily',          'ReconciliationHandler');

// Shipping
$router->add('/api/shipping/quote',                        'ShippingQuoteHandler');

// Webhooks
$router->add('/api/webhooks/efi/reprocess',                'EfiReprocessHandler');
$router->add('/api/webhooks/efi/retry',                    'EfiRetryHandler');
$router->add('/api/webhooks/efi',                          'EfiWebhookHandler');
$router->add('/api/webhooks/melhor-envio',                 'MelhorEnvioWebhookHandler');

// Products (mais específicas antes das genéricas)
$router->add('/api/products/:id/publish',                  'ProductPublishHandler');
$router->add('/api/products/:id',                          'ProductsByIdHandler');
$router->add('/api/products',                              'ProductsIndexHandler');

// Sellers
$router->add('/api/sellers/me/orders',                     'SellersMeOrdersHandler');
$router->add('/api/sellers/me/products',                   'SellersMeProductsHandler');
$router->add('/api/sellers/me',                            'SellersMeHandler');
$router->add('/api/sellers/:id',                           'SellersByIdHandler');

// Users
$router->add('/api/users/profile',                         'UserProfileHandler');
$router->add('/api/users/upgrade-to-vendor',               'UpgradeToVendorHandler');
$router->add('/api/users/addresses/:id',                   'AddressByIdHandler');
$router->add('/api/users/addresses',                       'AddressesHandler');

// -----------------------------------------------------------------------
// Dispatch
// -----------------------------------------------------------------------
try {
    $router->dispatch($path, $context);
} catch (Throwable $e) {
    error_log("[marketplace] Unhandled error: " . $e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine());
    Response::serverError('Erro interno do servidor');
}
