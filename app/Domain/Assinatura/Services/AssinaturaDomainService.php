<?php

declare(strict_types=1);

namespace App\Domain\Assinatura\Services;

use App\Modules\Assinatura\Models\Plano;
use Carbon\Carbon;

/**
 * Domain Service para regras de negócio de Assinatura
 * 
 * Centraliza lógica que não pertence a uma única entidade
 */
final class AssinaturaDomainService
{
    /**
     * Verifica se um plano é gratuito
     */
    public function isPlanoGratuito(Plano $plano, string $periodo = 'mensal'): bool
    {
        $valor = $periodo === 'anual' 
            ? ($plano->preco_anual ?? 0) 
            : ($plano->preco_mensal ?? 0);
            
        // 🔥 CORRIGIDO: Cast para float para evitar erros de comparação estrita (0 !== 0.00)
        return (float)$valor === 0.0 || $valor === null;
    }

    /**
     * Calcula o valor do plano baseado no período
     */
    public function calcularValor(Plano $plano, string $periodo = 'mensal'): float
    {
        if ($this->isPlanoGratuito($plano, $periodo)) {
            return 0.0;
        }

        return $periodo === 'anual' && $plano->preco_anual
            ? (float) $plano->preco_anual
            : (float) $plano->preco_mensal;
    }

    /**
     * Calcula a data de término da assinatura
     * 
     * IMPORTANTE: ADDSIMP usa ciclo individual de 30 dias corridos
     * O cliente é faturado a partir da data de contratação
     * O período contratado é sempre de 30 dias corridos
     * O ciclo de cobrança não depende do mês calendário
     * 
     * Exemplo: Cliente contrata em 18/03 → período vai de 18/03 a 17/04
     */
    public function calcularDataFim(Plano $plano, string $periodo = 'mensal', ?Carbon $dataInicio = null): Carbon
    {
        $dataInicio = $dataInicio ?? Carbon::now();

        // 🔥 NOVO: Respeitar campo de limitação de dias do plano, se existir
        // Convenção:
        // - > 0  => assinatura com duração fixa de N dias
        // - = 0  => ilimitado (sem vencimento prático)
        // - null => usa regra padrão (gratuito = 3 dias, pago = 30 dias)
        $limiteDias = $plano->limite_dias ?? null;

        if ($limiteDias !== null && $limiteDias !== '') {
            $limiteDiasInt = (int) $limiteDias;

            if ($limiteDiasInt > 0) {
                return $dataInicio->copy()->addDays($limiteDiasInt);
            }

            if ($limiteDiasInt === 0) {
                // Ilimitado: usamos uma data bem distante no futuro para representar
                return $dataInicio->copy()->addYears(100);
            }
        }

        // Fallback para planos gratuitos antigos (sem limite_dias configurado):
        if ($this->isPlanoGratuito($plano, $periodo)) {
            // Plano gratuito padrão: 3 dias de teste
            return $dataInicio->copy()->addDays(3);
        }

        // ADDSIMP: Sempre 30 dias corridos a partir da data de contratação
        // Independente do período (mensal/anual), o ciclo é sempre de 30 dias
        // Para planos anuais, serão múltiplos ciclos de 30 dias
        return $dataInicio->copy()->addDays(30);
    }

    /**
     * Calcula dias de grace period
     */
    public function calcularDiasGracePeriod(Plano $plano): int
    {
        if ($this->isPlanoGratuito($plano)) {
            return 0;
        }

        // Grace period padrão: 7 dias
        return 7;
    }

    /**
     * Determina o método de pagamento padrão baseado no plano
     */
    public function determinarMetodoPagamento(Plano $plano): string
    {
        if ($this->isPlanoGratuito($plano)) {
            return 'gratuito';
        }

        return 'pendente'; // Será atualizado quando o pagamento for processado
    }
}

