<?php

namespace App\Domain\AutorizacaoFornecimento\Enums;

/**
 * Enum para situação detalhada da autorização de fornecimento
 * 
 * Valores permitidos conforme migration:
 * - aguardando_empenho
 * - atendendo_empenho
 * - concluida
 * - parcialmente_atendida
 */
enum SituacaoDetalhadaAutorizacaoFornecimento: string
{
    case AGUARDANDO_EMPENHO = 'aguardando_empenho';
    case ATENDENDO_EMPENHO = 'atendendo_empenho';
    case CONCLUIDA = 'concluida';
    case PARCIALMENTE_ATENDIDA = 'parcialmente_atendida';

    public function label(): string
    {
        return match($this) {
            self::AGUARDANDO_EMPENHO => 'Aguardando Empenho',
            self::ATENDENDO_EMPENHO => 'Atendendo Empenho',
            self::CONCLUIDA => 'Concluída',
            self::PARCIALMENTE_ATENDIDA => 'Parcialmente Atendida',
        };
    }
}


