<?php

namespace App\Domain\Exceptions;

/**
 * Exceção para quando operação requer processo em execução mas não está
 */
class ProcessoNaoEmExecucaoException extends BusinessRuleException
{
    public function __construct(?string $message = null)
    {
        parent::__construct(
            $message ?? 'Esta operação requer que o processo esteja em execução.',
            'PROCESSO_NAO_EM_EXECUCAO',
            [],
            403
        );
    }
}




