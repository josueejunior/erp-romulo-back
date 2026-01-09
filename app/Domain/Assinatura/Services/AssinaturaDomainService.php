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
            
        return $valor === 0 || $valor === null;
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
     */
    public function calcularDataFim(Plano $plano, string $periodo = 'mensal', ?Carbon $dataInicio = null): Carbon
    {
        $dataInicio = $dataInicio ?? Carbon::now();
        
        if ($this->isPlanoGratuito($plano, $periodo)) {
            // Plano gratuito: 3 dias de teste
            return $dataInicio->copy()->addDays(3);
        }

        return match ($periodo) {
            'anual' => $dataInicio->copy()->addYear(),
            default => $dataInicio->copy()->addMonth(),
        };
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

