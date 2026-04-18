<?php

namespace App\Domain\Exceptions;

/**
 * Exceção para quando tentativa de operação em processo em execução
 */
class ProcessoEmExecucaoException extends BusinessRuleException
{
    public function __construct(?string $message = null)
    {
        parent::__construct(
            $message ?? 'Não é possível realizar esta operação em processos em execução.',
            'PROCESSO_EM_EXECUCAO',
            [],
            403
        );
    }
}









