<?php

declare(strict_types=1);

namespace App\Domain\Exceptions;

/**
 * Exceção para quando um email já está cadastrado mas a empresa está desativada
 * 
 * Retorna HTTP 409 (Conflict) e código semântico EMAIL_EMPRESA_DESATIVADA
 */
final class EmailEmpresaDesativadaException extends DomainException
{
    protected $code = 409;
    private string $email;

    public function __construct(
        string $email,
        ?string $customMessage = null,
        ?\Throwable $previous = null
    ) {
        $message = $customMessage ?? "Você já possui uma conta desativada. Entre em contato com o suporte para reativar sua conta.";
        
        parent::__construct(
            message: $message,
            code: 409,
            previous: $previous,
            errorCode: 'EMAIL_EMPRESA_DESATIVADA'
        );
        
        $this->email = $email;
    }

    public function getEmail(): string
    {
        return $this->email;
    }
}


