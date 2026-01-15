<?php

declare(strict_types=1);

namespace App\Domain\Exceptions;

/**
 * Exceção para credenciais inválidas no login
 * 
 * 🔥 SEGURANÇA: Sempre retorna mensagem genérica para evitar enumeração de emails
 * Retorna HTTP 401 (Unauthorized) e código semântico INVALID_CREDENTIALS
 */
final class CredenciaisInvalidasException extends DomainException
{
    protected $code = 401;
    
    public function __construct(
        string $message = 'Credenciais inválidas. Verifique seu e-mail e senha.',
        ?\Throwable $previous = null
    ) {
        parent::__construct(
            message: $message,
            code: 401,
            previous: $previous,
            errorCode: 'INVALID_CREDENTIALS'
        );
    }
}

