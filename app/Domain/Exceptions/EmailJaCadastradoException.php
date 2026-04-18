<?php

declare(strict_types=1);

namespace App\Domain\Exceptions;

/**
 * Exceção para quando um email já está cadastrado no sistema
 * 
 * Retorna HTTP 409 (Conflict) e código semântico EMAIL_EXISTS
 */
final class EmailJaCadastradoException extends DomainException
{
    protected $code = 409;
    private string $email;

    public function __construct(
        string $email,
        ?string $customMessage = null,
        ?\Throwable $previous = null
    ) {
        $message = $customMessage ?? "Este e-mail ({$email}) já está cadastrado no sistema. Faça login para acessar sua conta.";
        
        parent::__construct(
            message: $message,
            code: 409,
            previous: $previous,
            errorCode: 'EMAIL_EXISTS'
        );
        
        $this->email = $email;
    }

    public function getEmail(): string
    {
        return $this->email;
    }
}

