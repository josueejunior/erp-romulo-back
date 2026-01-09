<?php

namespace App\Domain\Exceptions;

/**
 * Exceção para quando tentativa de excluir AF com empenhos vinculados
 */
class AutorizacaoFornecimentoPossuiEmpenhosException extends BusinessRuleException
{
    public function __construct(?string $message = null)
    {
        parent::__construct(
            $message ?? 'Não é possível excluir uma Autorização de Fornecimento que possui empenhos vinculados.',
            'AF_POSSUI_EMPENHOS',
            [],
            403
        );
    }
}




