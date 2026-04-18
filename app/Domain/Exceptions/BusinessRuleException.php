<?php

namespace App\Domain\Exceptions;

/**
 * Exceção para violações de regras de negócio específicas
 * 
 * Permite rastrear qual regra foi violada e o contexto
 */
class BusinessRuleException extends DomainException
{
    public function __construct(
        string $message,
        public readonly string $rule,
        public readonly array $context = [],
        int $code = 400,
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
    }
    
    /**
     * Retorna dados estruturados para logging/debugging
     */
    public function toArray(): array
    {
        return [
            'message' => $this->getMessage(),
            'rule' => $this->rule,
            'context' => $this->context,
        ];
    }
}




