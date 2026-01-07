<?php

namespace App\Domain\Exceptions;

use Exception;

/**
 * Exceção base para erros de domínio
 * 
 * Usada para representar violações de regras de negócio
 * Retorna HTTP 400 (Bad Request)
 */
class DomainException extends Exception
{
    protected $code = 400;
    
    public function __construct(string $message = "", int $code = 400, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}




