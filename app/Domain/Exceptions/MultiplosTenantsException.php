<?php

declare(strict_types=1);

namespace App\Domain\Exceptions;

use Exception;

/**
 * Exceção lançada quando um email está associado a múltiplos tenants
 * 
 * O frontend deve exibir uma tela de seleção para o usuário escolher qual tenant acessar
 */
class MultiplosTenantsException extends Exception
{
    public function __construct(
        string $message = 'Este email está associado a múltiplas empresas. Selecione qual deseja acessar.',
        public readonly array $tenants = []
    ) {
        parent::__construct($message, 300); // HTTP 300 Multiple Choices
    }
}


