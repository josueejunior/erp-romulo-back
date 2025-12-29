<?php

namespace App\Domain\Exceptions;

/**
 * Exceção para operações não autorizadas
 * 
 * Retorna HTTP 403 (Forbidden)
 */
class UnauthorizedException extends DomainException
{
    protected $code = 403;
    
    public function __construct(
        string $message = 'Você não tem permissão para realizar esta operação',
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, 403, $previous);
    }
}

