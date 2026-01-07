<?php

namespace App\Domain\Exceptions;

use Exception;

/**
 * Exceção base para erros de domínio
 * 
 * Usada para representar violações de regras de negócio.
 * Suporta código de erro semântico além do código HTTP.
 */
class DomainException extends Exception
{
    protected $code = 400;
    protected ?string $errorCode = null;
    
    public function __construct(
        string $message = "",
        int $code = 400,
        ?\Throwable $previous = null,
        ?string $errorCode = null
    ) {
        parent::__construct($message, $code, $previous);
        $this->errorCode = $errorCode;
    }

    /**
     * Retorna o código de erro semântico (ex: EMAIL_EXISTS, CNPJ_EXISTS)
     */
    public function getErrorCode(): ?string
    {
        return $this->errorCode;
    }

    /**
     * Verifica se tem código de erro específico
     */
    public function hasErrorCode(): bool
    {
        return $this->errorCode !== null;
    }
}
