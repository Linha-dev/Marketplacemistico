<?php
/**
 * Response - Helpers padronizados de resposta da API.
 */
class Response
{
    private const API_VERSION = '1.0';

    public static function success(mixed $data, int $statusCode = 200): never
    {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode([
            'success' => true,
            'data'    => $data,
            'meta'    => [
                'timestamp' => gmdate('c'),
                'version'   => self::API_VERSION,
            ],
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    public static function error(
        string $code,
        string $message,
        int $statusCode = 400,
        mixed $details = null
    ): never {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=UTF-8');
        $error = ['code' => $code, 'message' => $message];
        if ($details !== null) {
            $error['details'] = $details;
        }
        echo json_encode([
            'success' => false,
            'error'   => $error,
            'meta'    => [
                'timestamp' => gmdate('c'),
                'version'   => self::API_VERSION,
            ],
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    public static function methodNotAllowed(): never
    {
        self::error('METHOD_NOT_ALLOWED', 'Método não permitido', 405);
    }

    public static function notFound(string $message = 'Recurso não encontrado'): never
    {
        self::error('NOT_FOUND', $message, 404);
    }

    public static function unauthorized(string $message = 'Não autenticado'): never
    {
        self::error('UNAUTHORIZED', $message, 401);
    }

    public static function forbidden(string $message = 'Acesso negado'): never
    {
        self::error('FORBIDDEN', $message, 403);
    }

    public static function serverError(string $message = 'Erro interno do servidor'): never
    {
        self::error('INTERNAL_ERROR', $message, 500);
    }

    public static function statusForCode(string $code): int
    {
        return match ($code) {
            'NOT_FOUND'        => 404,
            'METHOD_NOT_ALLOWED' => 405,
            'FORBIDDEN'        => 403,
            'UNAUTHORIZED'     => 401,
            'VALIDATION_ERROR',
            'INVALID_ID',
            'CANCEL_NOT_ALLOWED',
            'RETURN_NOT_ALLOWED',
            'INVALID_PAYMENT_STATUS',
            'NO_REFUNDABLE_BALANCE',
            'MULTI_SELLER_NOT_ALLOWED',
            'PRODUCT_UNAVAILABLE',
            'INSUFFICIENT_STOCK' => 400,
            default              => 500,
        };
    }
}
