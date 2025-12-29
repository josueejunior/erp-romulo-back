<?php

namespace App\Domain\Exceptions;

/**
 * Exceção para recursos não encontrados
 * 
 * Retorna HTTP 404 (Not Found)
 */
class NotFoundException extends DomainException
{
    protected $code = 404;
    
    public function __construct(
        string $resource = 'Recurso',
        ?string $identifier = null,
        ?\Throwable $previous = null
    ) {
        $message = $identifier 
            ? "{$resource} não encontrado (ID: {$identifier})"
            : "{$resource} não encontrado";
            
        parent::__construct($message, 404, $previous);
    }
}

