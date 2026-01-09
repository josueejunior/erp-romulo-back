<?php

namespace App\Domain\Exceptions;

/**
 * Exceção para quando tentativa de excluir contrato com empenhos vinculados
 */
class ContratoPossuiEmpenhosException extends BusinessRuleException
{
    public function __construct(?string $message = null)
    {
        parent::__construct(
            $message ?? 'Não é possível excluir um contrato que possui empenhos vinculados.',
            'CONTRATO_POSSUI_EMPENHOS',
            [],
            403
        );
    }
}




