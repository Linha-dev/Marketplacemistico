<?php
/**
 * BusinessException - Exceção de negócio com código de erro tipado.
 * Substitui o padrão de adicionar propriedade 'code' dinamicamente ao RuntimeException.
 */
class BusinessException extends RuntimeException
{
    private string $errorCode;

    public function __construct(string $errorCode, string $message, int $httpStatusHint = 0)
    {
        parent::__construct($message);
        $this->errorCode = $errorCode;
        // Guarda o HTTP status hint no $code padrão da RuntimeException (opcional)
        if ($httpStatusHint > 0) {
            parent::__construct($message, $httpStatusHint);
        }
    }

    public function getErrorCode(): string
    {
        return $this->errorCode;
    }
}
