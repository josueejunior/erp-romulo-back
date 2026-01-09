<?php

declare(strict_types=1);

namespace App\Domain\Exceptions;

/**
 * Exceção para quando um CNPJ já está cadastrado no sistema
 * 
 * Retorna HTTP 409 (Conflict) e código semântico CNPJ_EXISTS
 */
final class CnpjJaCadastradoException extends DomainException
{
    protected $code = 409;
    private string $cnpj;

    public function __construct(
        string $cnpj,
        ?\Throwable $previous = null
    ) {
        parent::__construct(
            message: "Este CNPJ ({$cnpj}) já está cadastrado no sistema. Se você é o responsável, faça login para acessar sua conta.",
            code: 409,
            previous: $previous,
            errorCode: 'CNPJ_EXISTS'
        );
        
        $this->cnpj = $cnpj;
    }

    public function getCnpj(): string
    {
        return $this->cnpj;
    }
}

