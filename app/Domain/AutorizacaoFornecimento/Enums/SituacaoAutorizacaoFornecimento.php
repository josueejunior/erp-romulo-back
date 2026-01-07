<?php

namespace App\Domain\AutorizacaoFornecimento\Enums;

/**
 * Enum para situação da autorização de fornecimento
 * 
 * Valores permitidos conforme migration:
 * - aguardando_empenho
 * - atendendo
 * - concluida
 */
enum SituacaoAutorizacaoFornecimento: string
{
    case AGUARDANDO_EMPENHO = 'aguardando_empenho';
    case ATENDENDO = 'atendendo';
    case CONCLUIDA = 'concluida';

    public function label(): string
    {
        return match($this) {
            self::AGUARDANDO_EMPENHO => 'Aguardando Empenho',
            self::ATENDENDO => 'Atendendo',
            self::CONCLUIDA => 'Concluída',
        };
    }
}


