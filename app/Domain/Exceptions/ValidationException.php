<?php

namespace App\Domain\Exceptions;

use Illuminate\Contracts\Validation\Validator;

/**
 * Exceção para erros de validação de domínio
 * 
 * Diferente de Illuminate\Validation\ValidationException,
 * esta é para validações de regras de negócio
 * Retorna HTTP 422 (Unprocessable Entity)
 */
class ValidationException extends DomainException
{
    protected $code = 422;
    
    public function __construct(
        string $message = 'Dados inválidos',
        public readonly array $errors = [],
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, 422, $previous);
    }
    
    /**
     * Criar a partir de um Validator do Laravel
     */
    public static function fromValidator(Validator $validator): self
    {
        return new self(
            'Dados inválidos',
            $validator->errors()->toArray()
        );
    }
}


