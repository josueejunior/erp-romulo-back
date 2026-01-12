<?php

namespace App\Domain\Exceptions;

/**
 * Exceção para quando uma entidade não pertence a outra (violação de vínculo)
 */
class EntidadeNaoPertenceException extends BusinessRuleException
{
    public function __construct(
        string $entidadeFilha,
        string $entidadePai,
        ?string $message = null
    ) {
        parent::__construct(
            $message ?? "{$entidadeFilha} não pertence ao {$entidadePai} informado.",
            'ENTIDADE_NAO_PERTENCE',
            [
                'entidade_filha' => $entidadeFilha,
                'entidade_pai' => $entidadePai,
            ],
            400
        );
    }
}





